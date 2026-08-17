<?php

namespace App\Services\CompanyRules;

/**
 * Derives readiness of the app's knowledge base (business_rules,
 * company_policies, employee_rules, security_compliance,
 * technical_standards, approval_governance -- see config/knowledge_rules.php)
 * before letting the AI project-creation chat start.
 *
 * The rulebook is now global (no per-company scoping -- rules are uploaded
 * once for the whole app, see KnowledgeBaseController), and rules are
 * persisted synchronously on upload (KnowledgeRuleChunker runs inline, no
 * background embedding pipeline), so there's no PROCESSING state to model
 * anymore: either rules exist or they don't.
 */
class CompanyRuleReadinessService
{
    public const NOT_CONFIGURED = 'NOT_CONFIGURED';

    public const READY_WITH_WARNINGS = 'READY_WITH_WARNINGS';

    public const READY = 'READY';

    /**
     * @return array{status: string, active_rule_count: int, processing_chunk_count: int, warnings: string[]}
     */
    public function evaluate(?int $companyId = null): array
    {
        $categories = config('knowledge_rules');

        $counts = [];
        foreach ($categories as $prefix => $meta) {
            $counts[$prefix] = $meta['model']::count();
        }

        $total = array_sum($counts);

        if ($total === 0) {
            return [
                'status' => self::NOT_CONFIGURED,
                'active_rule_count' => 0,
                'processing_chunk_count' => 0,
                'warnings' => [],
            ];
        }

        $warnings = $this->deriveWarnings($categories, $counts);

        return [
            'status' => empty($warnings) ? self::READY : self::READY_WITH_WARNINGS,
            'active_rule_count' => $total,
            'processing_chunk_count' => 0,
            'warnings' => $warnings,
        ];
    }

    /**
     * True when project creation must be blocked: the knowledge base has no
     * rules at all yet.
     */
    public function isBlocking(?int $companyId = null): bool
    {
        return $this->evaluate($companyId)['status'] === self::NOT_CONFIGURED;
    }

    /**
     * @return string[]
     */
    private function deriveWarnings(array $categories, array $counts): array
    {
        $warnings = [];

        if (($counts['BR'] ?? 0) === 0) {
            $warnings[] = 'No Business Rules (BR) -- project-type classification during AI chat creation may be unreliable.';
        }

        foreach ($categories as $prefix => $meta) {
            if (($counts[$prefix] ?? 0) === 0 && $prefix !== 'BR') {
                $warnings[] = "No {$meta['label']} ({$prefix}) rules uploaded yet.";
            }
        }

        return $warnings;
    }
}
