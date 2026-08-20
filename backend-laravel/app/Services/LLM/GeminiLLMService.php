<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiLLMService implements LLMService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function generate(string $prompt, bool $jsonMode = true): string
    {
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            // temperature 0: same reasoning as the Ollama implementation --
            // deterministic output reduces schema-compliance failures.
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => $jsonMode ? 'application/json' : 'text/plain',
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        try {
            $response = Http::timeout(120)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Gemini LLM request failed', ['error' => $e->getMessage(), 'model' => $this->model]);

            throw new LLMException("LLM provider (Gemini) unavailable: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            Log::error('Gemini LLM request returned an error', ['status' => $response->status(), 'body' => $response->body()]);

            throw new LLMException("LLM provider (Gemini) returned HTTP {$response->status()}.");
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            Log::error('Gemini LLM returned an empty response', ['model' => $this->model]);

            throw new LLMException('LLM provider returned an empty response.');
        }

        return $text;
    }
}
