<?php

namespace App\Providers;

use App\Services\LLM\GeminiLLMService;
use App\Services\LLM\LLMService;
use App\Services\LLM\OllamaLLMService;
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
        $this->app->bind(LLMService::class, function () {
            return match (config('llm.provider')) {
                'ollama' => new OllamaLLMService(
                    config('llm.ollama.base_url'),
                    config('llm.ollama.model'),
                ),
                'gemini' => new GeminiLLMService(
                    config('llm.gemini.api_key'),
                    config('llm.gemini.model'),
                ),
                default => throw new \RuntimeException('Unsupported LLM_PROVIDER: '.config('llm.provider')),
            };
        });

        $this->app->bind(PlaywrightWorkerRunner::class, ProcessPlaywrightWorkerRunner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
