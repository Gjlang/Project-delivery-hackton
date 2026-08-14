<?php

namespace App\Providers;

use App\Services\Embedding\EmbeddingService;
use App\Services\Embedding\OllamaEmbeddingService;
use App\Services\LLM\LLMService;
use App\Services\LLM\OllamaLLMService;
use App\Services\Qdrant\QdrantClient;
use App\Services\Testing\PlaywrightWorkerRunner;
use App\Services\Testing\ProcessPlaywrightWorkerRunner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmbeddingService::class, function () {
            return match (config('embeddings.provider')) {
                'ollama' => new OllamaEmbeddingService(
                    config('embeddings.ollama.base_url'),
                    config('embeddings.ollama.model'),
                ),
                default => throw new \RuntimeException('Unsupported EMBEDDING_PROVIDER: '.config('embeddings.provider')),
            };
        });

        $this->app->bind(LLMService::class, function () {
            return match (config('llm.provider')) {
                'ollama' => new OllamaLLMService(
                    config('llm.ollama.base_url'),
                    config('llm.ollama.model'),
                ),
                default => throw new \RuntimeException('Unsupported LLM_PROVIDER: '.config('llm.provider')),
            };
        });

        $this->app->bind(PlaywrightWorkerRunner::class, ProcessPlaywrightWorkerRunner::class);

        $this->app->singleton(QdrantClient::class, function () {
            return new QdrantClient(
                config('qdrant.url'),
                config('qdrant.api_key'),
                config('qdrant.collection'),
                config('qdrant.vector_size'),
                config('qdrant.distance'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
