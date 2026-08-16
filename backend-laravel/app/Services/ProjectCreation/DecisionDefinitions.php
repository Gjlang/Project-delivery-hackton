<?php

namespace App\Services\ProjectCreation;

/**
 * Extensible, data-only definition of the "decisions" the AI project-creation
 * chat resolves one at a time via targeted RuleRetrievalService calls, rather
 * than dumping the entire rulebook + chat history into a single LLM call.
 *
 * Adding a new decision (e.g. a company-specific compliance check) means
 * adding a new array entry here -- ProjectCreationChatService's orchestration
 * loop does not need to change.
 */
class DecisionDefinitions
{
    /**
     * @return array<string, array{label: string, query: string, categories: string[], top_k: int, required: bool}>
     */
    public static function all(): array
    {
        return [
            'required_info' => [
                'label' => 'Required project information',
                'query' => 'rules that define what information is required before a project can be planned',
                'categories' => ['BR'],
                'top_k' => 5,
                'required' => true,
            ],
            'project_type' => [
                'label' => 'Project type / category classification',
                'query' => 'rules that describe what kind of project this is and what category it belongs to',
                // Deliberately unscoped by category -- a company-custom category
                // (e.g. a "Loan Origination Project" rule) may define a type that
                // isn't BR at all.
                'categories' => [],
                'top_k' => 8,
                'required' => true,
            ],
            'authentication' => [
                'label' => 'Authentication & access control',
                'query' => 'rules that apply when a project has user authentication or login',
                // BR-029 (Authentication Feature) lives in BR, not SC/EW.
                'categories' => ['BR', 'SC'],
                'top_k' => 5,
                'required' => false,
            ],
            'integrations' => [
                'label' => 'Third-party integrations',
                'query' => 'rules that apply to third-party integrations or external systems',
                // BR-031 (External Integration) lives in BR, not CP/TS.
                'categories' => ['BR', 'CP'],
                'top_k' => 5,
                'required' => false,
            ],
            'compliance_constraints' => [
                'label' => 'Compliance and governance constraints',
                'query' => 'rules describing compliance, governance, or approval constraints for new projects',
                'categories' => ['AG', 'CP'],
                'top_k' => 5,
                'required' => false,
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return string[]
     */
    public static function requiredKeys(): array
    {
        return collect(self::all())
            ->filter(fn ($d) => $d['required'])
            ->keys()
            ->all();
    }
}
