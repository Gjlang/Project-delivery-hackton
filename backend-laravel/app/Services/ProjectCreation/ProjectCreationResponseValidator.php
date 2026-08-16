<?php

namespace App\Services\ProjectCreation;

/**
 * Validates/normalizes the LLM's per-decision JSON output. Mirrors
 * RequirementAnalysisValidator's tolerant-but-never-inventing approach:
 * recover what's reasonably there, but never trust the model to only touch
 * fields relevant to the current decision -- draft_patch keys and, for the
 * project_type decision, cited rule codes are both whitelisted here rather
 * than trusted from the model.
 */
class ProjectCreationResponseValidator
{
    private const ALLOWED_DRAFT_PATCH_KEYS = [
        'name', 'description', 'business_objective', 'requirements_raw', 'start_date',
        'has_authentication', 'protected_routes', 'roles', 'platforms', 'integrations',
    ];

    private const ALLOWED_ANALYSIS_STATUS = ['gathering', 'needs_clarification', 'ready'];

    private const ALLOWED_CONFIDENCE = ['low', 'medium', 'high'];

    /**
     * @return array{valid: bool, normalized: array<string, mixed>, warnings: string[]}
     */
    public function validate(mixed $decoded, string $decisionKey, array $allowedRuleCodes = []): array
    {
        if (! is_array($decoded)) {
            return ['valid' => false, 'normalized' => [], 'warnings' => ['LLM output was not a JSON object.']];
        }

        return $decisionKey === 'project_type'
            ? $this->validateProjectType($decoded, $allowedRuleCodes)
            : $this->validateGeneric($decoded);
    }

    private function validateGeneric(array $decoded): array
    {
        $warnings = [];

        $assistantMessage = $this->firstString($decoded, ['assistant_message']) ?? '';

        $patch = [];
        foreach ((array) ($decoded['draft_patch'] ?? []) as $key => $value) {
            if (! in_array($key, self::ALLOWED_DRAFT_PATCH_KEYS, true)) {
                $warnings[] = "Dropped disallowed draft_patch key [{$key}].";

                continue;
            }

            if (is_string($value) && $this->isSchemaPlaceholder(trim($value))) {
                continue;
            }

            $patch[$key] = $value;
        }

        $clarifications = $this->normalizeClarifications($decoded['clarifications'] ?? []);

        $status = $this->normalizeStatus($decoded['analysis_status'] ?? null, $warnings);

        return [
            'valid' => true,
            'normalized' => [
                'assistant_message' => $assistantMessage,
                'draft_patch' => $patch,
                'clarifications' => $clarifications,
                'analysis_status' => $status,
            ],
            'warnings' => $warnings,
        ];
    }

    private function validateProjectType(array $decoded, array $allowedRuleCodes): array
    {
        $warnings = [];

        $assistantMessage = $this->firstString($decoded, ['assistant_message']) ?? '';

        $primaryType = $this->firstString($decoded, ['primary_project_type']);

        $secondary = [];
        foreach ((array) ($decoded['secondary_project_types'] ?? []) as $value) {
            if (is_string($value) && trim($value) !== '' && ! $this->isSchemaPlaceholder(trim($value))) {
                $secondary[] = trim($value);
            }
        }

        $sourceRules = [];
        foreach ((array) ($decoded['source_rules'] ?? []) as $code) {
            if (! is_string($code)) {
                continue;
            }
            $code = trim($code);

            if (! in_array($code, $allowedRuleCodes, true)) {
                $warnings[] = "Dropped source_rules entry [{$code}] -- not among the retrieved candidate rules.";

                continue;
            }

            $sourceRules[] = $code;
        }

        // If the model named a type but cited zero rule codes we actually
        // retrieved, we can't trust the classification -- treat it as
        // low-confidence rather than inventing a source.
        if ($primaryType !== null && empty($sourceRules)) {
            $primaryType = null;
            $warnings[] = 'Primary type discarded: no valid source_rules were cited.';
        }

        $confidence = is_string($decoded['confidence'] ?? null) && in_array(strtolower($decoded['confidence']), self::ALLOWED_CONFIDENCE, true)
            ? strtolower($decoded['confidence'])
            : 'low';

        $clarifications = $this->normalizeClarifications($decoded['clarifications'] ?? []);
        $status = $this->normalizeStatus($decoded['analysis_status'] ?? null, $warnings);

        return [
            'valid' => true,
            'normalized' => [
                'assistant_message' => $assistantMessage,
                'primary_project_type' => $primaryType,
                'secondary_project_types' => array_values(array_unique($secondary)),
                'source_rules' => array_values(array_unique($sourceRules)),
                'confidence' => $confidence,
                'clarifications' => $clarifications,
                'analysis_status' => $status,
            ],
            'warnings' => $warnings,
        ];
    }

    private function normalizeClarifications(mixed $items): array
    {
        $normalized = [];
        foreach ((array) $items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = $this->firstString($item, ['question']);
            if ($question === null) {
                continue;
            }

            $normalized[] = [
                'field' => $this->firstString($item, ['field']),
                'question' => $question,
                'reason' => $this->firstString($item, ['reason']),
            ];
        }

        return $normalized;
    }

    private function normalizeStatus(mixed $status, array &$warnings): string
    {
        if (is_string($status) && in_array(strtolower($status), self::ALLOWED_ANALYSIS_STATUS, true)) {
            return strtolower($status);
        }

        $warnings[] = 'Missing/invalid analysis_status, defaulted to "gathering".';

        return 'gathering';
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

    private function isSchemaPlaceholder(string $value): bool
    {
        return in_array(strtolower($value), ['string', 'null', 'n/a', 'none', 'todo', '<extracted text>'], true);
    }
}
