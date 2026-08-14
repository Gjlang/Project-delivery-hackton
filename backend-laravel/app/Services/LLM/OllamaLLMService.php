<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaLLMService implements LLMService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
    ) {
    }

    public function generate(string $prompt, bool $jsonMode = true): string
    {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            // temperature 0: this model is small enough already without also
            // letting sampling randomness add to schema-compliance failures.
            'options' => ['temperature' => 0],
        ];

        if ($jsonMode) {
            $payload['format'] = 'json';
        }

        try {
            $response = Http::timeout(120)
                ->post(rtrim($this->baseUrl, '/').'/api/generate', $payload);
        } catch (\Throwable $e) {
            Log::error('Ollama LLM request failed', ['error' => $e->getMessage(), 'model' => $this->model]);

            throw new LLMException("LLM provider (Ollama) unavailable: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            Log::error('Ollama LLM request returned an error', ['status' => $response->status(), 'body' => $response->body()]);

            throw new LLMException("LLM provider (Ollama) returned HTTP {$response->status()}.");
        }

        $text = $response->json('response');

        if (! is_string($text) || trim($text) === '') {
            Log::error('Ollama LLM returned an empty response', ['model' => $this->model]);

            throw new LLMException('LLM provider returned an empty response.');
        }

        return $text;
    }
}
