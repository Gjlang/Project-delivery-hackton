<?php

namespace App\Services\Rules;

use App\Models\CompanyRule;
use App\Models\RuleChunk;
use App\Services\Embedding\EmbeddingService;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Support\Facades\Log;

/**
 * Semantic candidate search only. Qdrant tells us which rules are
 * semantically relevant to a query; the returned rule content always comes
 * from a fresh MySQL load, never from the Qdrant payload.
 */
class RuleRetrievalService
{
    private const DEFAULT_TOP_K = 5;

    /**
     * How many raw chunk matches to pull from Qdrant before deduplicating
     * down to distinct rules -- a rule can have several matching chunks, so
     * asking for exactly top_k points would under-fill the deduplicated
     * result.
     */
    private const RAW_SEARCH_MULTIPLIER = 4;

    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly QdrantClient $qdrant,
    ) {
    }

    /**
     * @param  string[]  $categories  Category codes, e.g. ['EW'] or ['SC', 'TS']. Empty = no category filter.
     * @return array{query: string, results: array<int, array<string, mixed>>}
     */
    public function retrieve(int $companyId, string $query, array $categories = [], ?int $topK = null): array
    {
        $query = trim($query);
        $topK = $topK ?: self::DEFAULT_TOP_K;

        if ($query === '') {
            return ['query' => $query, 'results' => []];
        }

        $vectors = $this->embeddings->embed([$query]);
        $vector = $vectors[0] ?? null;

        if (! $vector) {
            Log::warning('Rule retrieval: query embedding returned no vector', ['company_id' => $companyId, 'query' => $query]);

            return ['query' => $query, 'results' => []];
        }

        $filter = ['must' => [
            ['key' => 'company_id', 'match' => ['value' => $companyId]],
            ['key' => 'is_active', 'match' => ['value' => true]],
            ['key' => 'status', 'match' => ['value' => 'active']],
        ]];

        if (! empty($categories)) {
            $filter['must'][] = ['key' => 'category', 'match' => ['any' => array_values($categories)]];
        }

        $rawLimit = max($topK * self::RAW_SEARCH_MULTIPLIER, 20);
        $matches = $this->qdrant->search($vector, $rawLimit, $filter);

        Log::info('Rule retrieval search executed', ['company_id' => $companyId, 'query' => $query, 'categories' => $categories, 'raw_matches' => count($matches)]);

        if (empty($matches)) {
            return ['query' => $query, 'results' => []];
        }

        // Best chunk score per rule.
        $bestPerRule = [];
        foreach ($matches as $match) {
            $ruleId = $match['payload']['company_rule_id'] ?? null;
            if (! $ruleId) {
                continue;
            }

            if (! isset($bestPerRule[$ruleId]) || $match['score'] > $bestPerRule[$ruleId]['score']) {
                $bestPerRule[$ruleId] = $match;
            }
        }

        uasort($bestPerRule, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($bestPerRule, 0, $topK, true);

        $chunkIds = array_column($top, 'id');
        $chunkTextById = RuleChunk::whereIn('id', $chunkIds)->pluck('chunk_text', 'id');

        $ruleIds = array_keys($top);
        $rules = CompanyRule::with(['category', 'parameters'])
            ->whereIn('id', $ruleIds)
            ->where('company_id', $companyId) // defense in depth: never trust the index alone for isolation
            ->get()
            ->keyBy('id');

        $results = [];
        foreach ($top as $ruleId => $match) {
            $rule = $rules->get($ruleId);
            if (! $rule) {
                continue; // rule vanished/renamed company since last sync -- skip rather than return stale data
            }

            $results[] = [
                'company_rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'category' => $rule->category?->code,
                'title' => $rule->title,
                'similarity_score' => round($match['score'], 4),
                'matched_chunk' => $chunkTextById->get($match['id']),
                'rule' => [
                    'rule_text' => $rule->rule_text,
                    'applicable_condition' => $rule->applicable_condition,
                    'required_behavior' => $rule->required_behavior,
                    'expected_outcome' => $rule->expected_outcome,
                    'parameters' => $rule->parameters->map(fn ($p) => [
                        'key' => $p->parameter_key,
                        'value' => $p->parameter_value,
                        'unit' => $p->unit,
                    ])->values(),
                ],
            ];
        }

        return ['query' => $query, 'results' => $results];
    }
}
