<?php

return [

    // Which driver implements App\Services\LLM\LLMService. This is a
    // generative text model -- distinct from EMBEDDING_PROVIDER, which only
    // produces vectors and cannot do requirement analysis.
    'provider' => env('LLM_PROVIDER', 'ollama'),

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_LLM_MODEL', 'llama3.2:1b'),
    ],

    'gemini' => [
        'api_key' => env('GOOGLE_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    ],

];
