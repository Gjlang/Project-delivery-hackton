<?php

namespace App\Services\CompanyRules;

use App\Models\CompanyRule;
use App\Models\RuleChunk;

/**
 * Derives a company's rule-readiness state purely from existing data --
 * no dedicated table. Project creation (both the AI chat flow and the
 * legacy form) is gated on this so a company can never start a rule-aware
 * flow before it actually has searchable rules.
 */
class CompanyRuleReadinessService
{
    public const NOT_CONFIGURED = 'NOT_CONFIGURED';

    public const PROCESSING = 'PROCESSING';

    public const READY_WITH_WARNINGS = 'READY_WITH_WARNINGS';

    public const READY = 'READY';

    /**
     * @return array{status: string, active_rule_count: int, processing_chunk_count: int, warnings: string[]}
     */
    public function evaluate(int $companyId): array
    {
        $activeCount = CompanyRule::forCompany($companyId)->active()->count();

        if ($activeCount === 0) {
            return [
                'status' => self::NOT_CONFIGURED,
                'active_rule_count' => 0,
                'processing_chunk_count' => 0,
                'warnings' => [],
            ];
        }

        $processingChunks = RuleChunk::whereHas('companyRule', fn ($q) => $q->forCompany($companyId)->active())
            ->where('embedding_status', '!=', 'embedded')
            ->count();

        if ($processingChunks > 0) {
            return [
                'status' => self::PROCESSING,
                'active_rule_count' => $activeCount,
                'processing_chunk_count' => $processingChunks,
                'warnings' => [],
            ];
        }

        $warnings = $this->deriveWarnings($companyId);

        return [
            'status' => empty($warnings) ? self::READY : self::READY_WITH_WARNINGS,
            'active_rule_count' => $activeCount,
            'processing_chunk_count' => 0,
            'warnings' => $warnings,
        ];
    }

    /**
     * True when project creation must be blocked: no active rules at all, or
     * rules exist but their embeddings are not yet searchable in Qdrant (a
     * chat session relying on rule retrieval mid-conversation would silently
     * degrade if started here).
     */
    public function isBlocking(int $companyId): bool
    {
        $status = $this->evaluate($companyId)['status'];

        return in_array($status, [self::NOT_CONFIGURED, self::PROCESSING], true);
    }

    /**
     * @return string[]
     */
    private function deriveWarnings(int $companyId): array
    {
        $warnings = [];

        if (! CompanyRule::forCompany($companyId)->active()->where('rule_category_id', function ($query) {
            $query->select('id')->from('rule_categories')->where('code', 'BR')->limit(1);
        })->exists()) {
            $warnings[] = 'No active Business Rules (BR) -- project-type classification during AI chat creation may be unreliable.';
        }

        return $warnings;
    }
}
