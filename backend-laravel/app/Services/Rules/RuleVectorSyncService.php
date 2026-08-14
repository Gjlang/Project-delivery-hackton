<?php

namespace App\Services\Rules;

use App\Models\CompanyRule;
use App\Models\RuleChunk;
use App\Services\Embedding\EmbeddingException;
use App\Services\Embedding\EmbeddingService;
use App\Services\Qdrant\QdrantClient;
use App\Services\Qdrant\QdrantException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates: company_rules -> chunks -> embeddings -> Qdrant points.
 * Qdrant point id == rule_chunks.id, so re-upserting an unchanged chunk is a
 * no-op overwrite rather than a duplicate, which is what makes the whole
 * pipeline safe to re-run.
 */
class RuleVectorSyncService
{
    public function __construct(
        private readonly RuleChunkingService $chunker,
        private readonly EmbeddingService $embeddings,
        private readonly QdrantClient $qdrant,
    ) {
    }

    /**
     * @param  Collection<int, CompanyRule>  $rules
     * @return array{rules_processed: int, chunks_created: int, chunks_updated: int, chunks_unchanged: int, chunks_deleted: int, chunks_embedded: int, chunks_failed: int, stale_chunks_removed: int}
     */
    public function syncRules(Collection $rules): array
    {
        $this->qdrant->ensureCollection();

        $summary = [
            'rules_processed' => 0,
            'chunks_created' => 0,
            'chunks_updated' => 0,
            'chunks_unchanged' => 0,
            'chunks_deleted' => 0,
            'chunks_embedded' => 0,
            'chunks_failed' => 0,
            'stale_chunks_removed' => 0,
        ];

        /** @var Collection<int, RuleChunk> $pendingChunks */
        $pendingChunks = collect();
        $deletedIdsAcrossRules = [];

        foreach ($rules as $rule) {
            $result = $this->chunker->sync($rule);
            $summary['rules_processed']++;

            foreach ($result['chunks'] as $chunk) {
                if (in_array($chunk->id, $result['changed_ids'], true)) {
                    $pendingChunks->push($chunk);
                } else {
                    $summary['chunks_unchanged']++;
                }
            }

            $summary['chunks_created'] += count($result['created_ids']);
            $summary['chunks_updated'] += count($result['updated_ids']);
            $summary['chunks_deleted'] += count($result['deleted_ids']);
            $deletedIdsAcrossRules = [...$deletedIdsAcrossRules, ...$result['deleted_ids']];
        }

        if (! empty($deletedIdsAcrossRules)) {
            $this->qdrant->deleteByIds($deletedIdsAcrossRules);
        }

        [$embedded, $failed] = $this->embedAndUpsert($pendingChunks);
        $summary['chunks_embedded'] = $embedded;
        $summary['chunks_failed'] = $failed;

        $summary['stale_chunks_removed'] = $this->removeStaleChunks();

        return $summary;
    }

    /**
     * @param  Collection<int, RuleChunk>  $chunks
     * @return array{0: int, 1: int} [embeddedCount, failedCount]
     */
    private function embedAndUpsert(Collection $chunks): array
    {
        if ($chunks->isEmpty()) {
            return [0, 0];
        }

        // $chunks was built by push()-ing onto a plain Support\Collection,
        // which has no loadMissing() -- wrap it as an Eloquent collection to
        // eager-load the relation instead of N+1 lazy-loading it below.
        \Illuminate\Database\Eloquent\Collection::make($chunks->all())->loadMissing('companyRule.category');

        try {
            $vectors = $this->embeddings->embed($chunks->pluck('chunk_text')->all());
        } catch (EmbeddingException $e) {
            Log::error('Rule chunk embedding batch failed entirely', ['error' => $e->getMessage(), 'chunk_count' => $chunks->count()]);
            RuleChunk::whereIn('id', $chunks->pluck('id'))->update(['embedding_status' => 'failed']);

            return [0, $chunks->count()];
        }

        $points = [];
        foreach ($chunks->values() as $i => $chunk) {
            $rule = $chunk->companyRule;

            $points[] = [
                'id' => $chunk->id,
                'vector' => $vectors[$i],
                'payload' => [
                    'company_rule_id' => $rule->id,
                    'rule_code' => $rule->rule_code,
                    'company_id' => $rule->company_id,
                    'category' => $rule->category?->code,
                    'version' => $rule->version,
                    'status' => $rule->status,
                    'is_active' => (bool) $rule->is_active,
                    'chunk_index' => $chunk->chunk_index,
                    'source_document_id' => $rule->source_document_id,
                ],
            ];
        }

        try {
            $this->qdrant->upsertPoints($points);
        } catch (QdrantException $e) {
            Log::error('Qdrant upsert failed for rule chunk batch', ['error' => $e->getMessage(), 'chunk_count' => count($points)]);
            RuleChunk::whereIn('id', $chunks->pluck('id'))->update(['embedding_status' => 'failed']);

            return [0, $chunks->count()];
        }

        // external_vector_id equals the row's own id (that's the Qdrant
        // point id), so it's set per-row rather than in the bulk update.
        RuleChunk::whereIn('id', $chunks->pluck('id'))->update(['embedding_status' => 'embedded']);
        foreach ($chunks as $chunk) {
            $chunk->forceFill(['external_vector_id' => (string) $chunk->id])->save();
        }

        return [count($points), 0];
    }

    /**
     * Chunks whose parent rule was archived/deactivated/soft-deleted since
     * the last sync no longer belong in the semantic index.
     */
    private function removeStaleChunks(): int
    {
        $staleChunks = RuleChunk::whereDoesntHave('companyRule')
            ->orWhereHas('companyRule', function ($q) {
                $q->where('is_active', false)->orWhere('status', '!=', 'active');
            })
            ->where('embedding_status', 'embedded')
            ->get();

        if ($staleChunks->isEmpty()) {
            return 0;
        }

        $this->qdrant->deleteByIds($staleChunks->pluck('id')->all());

        RuleChunk::whereIn('id', $staleChunks->pluck('id'))->update([
            'embedding_status' => 'outdated',
            'external_vector_id' => null,
        ]);

        return $staleChunks->count();
    }
}
