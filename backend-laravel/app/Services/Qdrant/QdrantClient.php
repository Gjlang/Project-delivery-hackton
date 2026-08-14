<?php

namespace App\Services\Qdrant;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin REST wrapper around Qdrant. Qdrant is a semantic index only -- it is
 * never treated as the source of truth. Every point payload carries just
 * enough metadata to filter and to look the authoritative row back up in
 * MySQL (company_rule_id).
 */
class QdrantClient
{
    private const UPSERT_BATCH_SIZE = 100;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $collection,
        private readonly int $vectorSize,
        private readonly string $distance,
    ) {
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl(rtrim($this->baseUrl, '/'))->timeout(30)->acceptJson();

        if ($this->apiKey) {
            $request = $request->withHeaders(['api-key' => $this->apiKey]);
        }

        return $request;
    }

    /**
     * Create the shared collection if it doesn't already exist. Safe to call
     * repeatedly.
     */
    public function ensureCollection(): void
    {
        $existing = $this->http()->get("/collections/{$this->collection}");

        if ($existing->successful()) {
            return;
        }

        $response = $this->http()->put("/collections/{$this->collection}", [
            'vectors' => [
                'size' => $this->vectorSize,
                'distance' => $this->distance,
            ],
        ]);

        if ($response->failed()) {
            Log::error('Qdrant collection creation failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new QdrantException("Failed to create Qdrant collection [{$this->collection}]: HTTP {$response->status()}.");
        }

        Log::info('Qdrant collection created', ['collection' => $this->collection]);
    }

    /**
     * Upsert points (id/vector/payload). Upserting an existing point id
     * overwrites it in place -- callers should use deterministic ids
     * (rule_chunks.id) so re-running sync never creates duplicates.
     *
     * @param  array<int, array{id: int, vector: array<int, float>, payload: array<string, mixed>}>  $points
     */
    public function upsertPoints(array $points): void
    {
        foreach (array_chunk($points, self::UPSERT_BATCH_SIZE) as $batch) {
            $response = $this->http()->put("/collections/{$this->collection}/points", [
                'points' => array_values($batch),
            ]);

            if ($response->failed()) {
                Log::error('Qdrant upsert failed', ['status' => $response->status(), 'body' => $response->body(), 'batch_size' => count($batch)]);

                throw new QdrantException("Qdrant upsert failed: HTTP {$response->status()}.");
            }
        }

        Log::info('Qdrant upsert complete', ['collection' => $this->collection, 'points' => count($points)]);
    }

    /**
     * Delete points matching a filter (e.g. all chunks for an archived rule).
     *
     * @param  array<string, mixed>  $filter
     */
    public function deleteByFilter(array $filter): void
    {
        $response = $this->http()->post("/collections/{$this->collection}/points/delete", [
            'filter' => $filter,
        ]);

        if ($response->failed()) {
            Log::error('Qdrant delete failed', ['status' => $response->status(), 'body' => $response->body(), 'filter' => $filter]);

            throw new QdrantException("Qdrant delete failed: HTTP {$response->status()}.");
        }
    }

    /**
     * Delete points by their exact ids (used when we already know which
     * rule_chunks rows were removed on the MySQL side).
     *
     * @param  int[]  $ids
     */
    public function deleteByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $response = $this->http()->post("/collections/{$this->collection}/points/delete", [
            'points' => array_values($ids),
        ]);

        if ($response->failed()) {
            Log::error('Qdrant delete-by-id failed', ['status' => $response->status(), 'body' => $response->body(), 'ids' => $ids]);

            throw new QdrantException("Qdrant delete-by-id failed: HTTP {$response->status()}.");
        }
    }

    /**
     * @param  array<int, float>  $vector
     * @param  array<string, mixed>  $filter
     * @return array<int, array{id: int, score: float, payload: array<string, mixed>}>
     */
    public function search(array $vector, int $limit, array $filter = []): array
    {
        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ];

        if (! empty($filter)) {
            $body['filter'] = $filter;
        }

        $response = $this->http()->post("/collections/{$this->collection}/points/search", $body);

        if ($response->failed()) {
            Log::error('Qdrant search failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new QdrantException("Qdrant search failed: HTTP {$response->status()}.");
        }

        return $response->json('result') ?? [];
    }
}
