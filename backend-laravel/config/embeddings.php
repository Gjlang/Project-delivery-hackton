<?php

return [

    // Which driver implements App\Services\Embedding\EmbeddingService.
    'provider' => env('EMBEDDING_PROVIDER', 'ollama'),

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    ],

];
