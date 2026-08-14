<?php

namespace App\Console\Commands;

use App\Models\CompanyRule;
use App\Services\Rules\RuleVectorSyncService;
use Illuminate\Console\Command;

class SyncRuleVectors extends Command
{
    protected $signature = 'rules:sync-vectors
        {--company= : Only sync rules for this company id}
        {--category= : Only sync rules in this category code, e.g. EW}
        {--rule= : Only sync a single rule by its rule_code, e.g. EW-008}';

    protected $description = 'Chunk active company rules, embed the chunks, and upsert them into Qdrant (idempotent).';

    public function handle(RuleVectorSyncService $sync): int
    {
        $query = CompanyRule::active()->with('category');

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        if ($category = $this->option('category')) {
            $query->whereHas('category', fn ($q) => $q->where('code', $category));
        }

        if ($ruleCode = $this->option('rule')) {
            $query->where('rule_code', $ruleCode);
        }

        $rules = $query->get();

        if ($rules->isEmpty()) {
            $this->warn('No matching active rules found.');

            return self::SUCCESS;
        }

        $this->info("Syncing {$rules->count()} active rule(s)...");

        $summary = $sync->syncRules($rules);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Rules processed', $summary['rules_processed']],
                ['Chunks created', $summary['chunks_created']],
                ['Chunks updated', $summary['chunks_updated']],
                ['Chunks unchanged (skipped)', $summary['chunks_unchanged']],
                ['Chunks deleted (trailing)', $summary['chunks_deleted']],
                ['Chunks embedded + upserted', $summary['chunks_embedded']],
                ['Chunks failed', $summary['chunks_failed']],
                ['Stale chunks removed (archived/inactive rules)', $summary['stale_chunks_removed']],
            ]
        );

        if ($summary['chunks_failed'] > 0) {
            $this->error("{$summary['chunks_failed']} chunk(s) failed to embed/upsert -- see logs for details.");

            return self::FAILURE;
        }

        $this->info('Sync complete.');

        return self::SUCCESS;
    }
}
