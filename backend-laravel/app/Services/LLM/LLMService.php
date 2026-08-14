<?php

namespace App\Services\LLM;

interface LLMService
{
    /**
     * Generate a completion for the given prompt. When $jsonMode is true,
     * the provider is asked (where supported) to constrain output to
     * syntactically valid JSON -- this does NOT guarantee the JSON matches
     * any particular schema, only that it parses.
     *
     * @throws LLMException
     */
    public function generate(string $prompt, bool $jsonMode = true): string;
}
