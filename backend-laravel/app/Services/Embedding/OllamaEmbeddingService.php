<?php

namespace App\Services\Embedding;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaEmbeddingService implements EmbeddingService
{
    /**
     * Ollama's /api/embed accepts a batch, but very large batches risk long
     * single requests with no partial progress on failure -- send in
     * moderate sub-batches instead of one call per chunk.
     */
    private const BATCH_SIZE = 32;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
    ) {
    }

    public function embed(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $vectors = [];

        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            $vectors = [...$vectors, ...$this->embedBatch($batch)];
        }

        return $vectors;
    }

    /**
     * @param  string[]  $batch
     * @return array<int, array<int, float>>
     */
    private function embedBatch(array $batch): array
    {
        try {
            $response = Http::timeout(60)
                ->post(rtrim($this->baseUrl, '/').'/api/embed', [
                    'model' => $this->model,
                    'input' => array_values($batch),
                ]);
        } catch (\Throwable $e) {
            Log::error('Ollama embedding request failed', ['error' => $e->getMessage(), 'batch_size' => count($batch)]);

            throw new EmbeddingException("Embedding provider (Ollama) unavailable: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            Log::error('Ollama embedding request returned an error', ['status' => $response->status(), 'body' => $response->body()]);

            throw new EmbeddingException("Embedding provider (Ollama) returned HTTP {$response->status()}.");
        }

        $embeddings = $response->json('embeddings');

        if (! is_array($embeddings) || count($embeddings) !== count($batch)) {
            Log::error('Ollama embedding response shape mismatch', ['expected' => count($batch), 'got' => is_array($embeddings) ? count($embeddings) : null]);

            throw new EmbeddingException('Embedding provider returned an unexpected number of vectors.');
        }

        return $embeddings;
    }
}
