<?php

namespace App\Services\Embedding;

interface EmbeddingService
{
    /**
     * Embed a batch of texts, returning one vector per input text in the
     * same order. Documents and queries use the same embedding space for
     * nomic-embed-text, so a single method covers both call sites.
     *
     * @param  string[]  $texts
     * @return array<int, array<int, float>>
     *
     * @throws EmbeddingException
     */
    public function embed(array $texts): array;
}
