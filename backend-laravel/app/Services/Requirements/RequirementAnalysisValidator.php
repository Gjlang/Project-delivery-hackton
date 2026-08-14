<?php

namespace App\Services\Requirements;

/**
 * Validates and normalizes the LLM's JSON output against the schema
 * RequirementAnalysisPromptBuilder asks for. Small local models frequently
 * rename keys (camelCase instead of snake_case) or omit a category entirely
 * -- this recovers what it reasonably can per-field rather than rejecting
 * the whole analysis over one malformed section, while never inventing
 * content that wasn't in the model's output.
 */
class RequirementAnalysisValidator
{
    private const ARRAY_OF_OBJECT_FIELDS = [
        'major_features',
        'functional_requirements',
        'non_functional_requirements',
        'business_constraints',
        'integrations',
        'data_requirements',
        'clarifications_required',
    ];

    private const ARRAY_OF_STRING_FIELDS = ['user_roles', 'platforms', 'assumptions'];

    private const ALLOWED_PRIORITIES = ['critical', 'high', 'medium', 'low'];

    /** Tolerates common small-model key variations without inventing data. */
    private const KEY_ALIASES = [
        'majorFeatures' => 'major_features',
        'features' => 'major_features',
        'functionalRequirements' => 'functional_requirements',
        'nonFunctionalRequirements' => 'non_functional_requirements',
        'non_functional' => 'non_functional_requirements',
        'businessConstraints' => 'business_constraints',
        'constraints' => 'business_constraints',
        'dataRequirements' => 'data_requirements',
        'userRoles' => 'user_roles',
        'roles' => 'user_roles',
        'clarificationsRequired' => 'clarifications_required',
        'clarifications' => 'clarifications_required',
    ];

    /**
     * @return array{valid: bool, normalized: array<string, mixed>, warnings: string[]}
     */
    public function validate(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return ['valid' => false, 'normalized' => $this->emptySkeleton(), 'warnings' => ['LLM output was not a JSON object.']];
        }

        $decoded = $this->applyAliases($decoded, self::KEY_ALIASES);
        $warnings = [];
        $normalized = $this->emptySkeleton();

        foreach (self::ARRAY_OF_OBJECT_FIELDS as $field) {
            $items = is_array($decoded[$field] ?? null) ? $decoded[$field] : [];
            if (! isset($decoded[$field])) {
                $warnings[] = "Missing field [{$field}], defaulted to empty.";
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $item = $this->applyAliases($item, [
                    'sourceText' => 'source_text',
                    'isMandatory' => 'mandatory',
                    'is_mandatory' => 'mandatory',
                ]);

                $name = $this->firstString($item, ['name', 'title']);
                $sourceText = $this->firstString($item, ['source_text', 'text']);

                if ($name === null && $sourceText === null) {
                    continue; // nothing identifiable to keep
                }

                $entry = [
                    'name' => $name,
                    'description' => $this->firstString($item, ['description', 'purpose']),
                    'source_text' => $sourceText,
                    'priority' => $this->normalizePriority($item['priority'] ?? null),
                    'mandatory' => (bool) ($item['mandatory'] ?? false),
                ];

                if ($field === 'integrations') {
                    $entry['purpose'] = $this->firstString($item, ['purpose', 'description']);
                }

                if ($field === 'clarifications_required') {
                    $entry['reason'] = $this->firstString($item, ['reason']);
                    $entry['clarification_question'] = $this->firstString($item, ['clarification_question', 'question']);
                }

                // Drop only genuinely absent keys (nulls); keep `false`/`0`.
                $normalized[$field][] = array_filter($entry, fn ($v) => $v !== null);
            }
        }

        foreach (self::ARRAY_OF_STRING_FIELDS as $field) {
            $items = is_array($decoded[$field] ?? null) ? $decoded[$field] : [];
            if (! isset($decoded[$field])) {
                $warnings[] = "Missing field [{$field}], defaulted to empty.";
            }

            foreach ($items as $item) {
                if (is_string($item) && trim($item) !== '' && ! $this->isSchemaPlaceholder(trim($item))) {
                    $normalized[$field][] = trim($item);
                } elseif (is_array($item)) {
                    $value = $this->firstString($item, ['name', 'value', 'text']);
                    if ($value !== null) {
                        $normalized[$field][] = $value;
                    }
                }
            }

            $normalized[$field] = array_values(array_unique($normalized[$field]));
        }

        return ['valid' => true, 'normalized' => $normalized, 'warnings' => $warnings];
    }

    private function emptySkeleton(): array
    {
        $skeleton = [];
        foreach ([...self::ARRAY_OF_OBJECT_FIELDS, ...self::ARRAY_OF_STRING_FIELDS] as $field) {
            $skeleton[$field] = [];
        }

        return $skeleton;
    }

    private function applyAliases(array $data, array $aliases): array
    {
        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $data) && ! array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }
        }

        return $data;
    }

    private function firstString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && is_string($item[$key])) {
                $value = trim($item[$key]);
                if ($value !== '' && ! $this->isSchemaPlaceholder($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Small local models sometimes echo the JSON schema's own type
     * placeholders ("string") back as literal values instead of real
     * content -- treat that the same as a missing value rather than
     * persisting it as if it were real project information.
     */
    private function isSchemaPlaceholder(string $value): bool
    {
        return in_array(strtolower($value), ['string', 'null', 'n/a', 'none', 'todo', '<extracted text>'], true);
    }

    private function normalizePriority(mixed $priority): ?string
    {
        if (! is_string($priority)) {
            return null;
        }

        $priority = strtolower(trim($priority));

        return in_array($priority, self::ALLOWED_PRIORITIES, true) ? $priority : null;
    }
}
