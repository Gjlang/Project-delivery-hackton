<?php

namespace App\Services\Rules;

use App\Models\CompanyRule;
use App\Models\RuleChunk;
use Illuminate\Support\Collection;

/**
 * Turns a CompanyRule into one or more self-contained text chunks and syncs
 * them into rule_chunks. A chunk must read sensibly on its own, so the
 * default is 1 rule = 1 chunk (our rule_text already carries the full
 * original wording); only rules whose header + body exceeds the safety
 * length threshold are split, and only on paragraph boundaries.
 */
class RuleChunkingService
{
    /**
     * Rough token-length safety valve (~4 chars/token for English prose).
     * None of the current 158 rules are anywhere near this -- it exists so a
     * future unusually long rule degrades gracefully instead of becoming one
     * giant chunk.
     */
    private const MAX_CHUNK_CHARS = 2000;

    /**
     * Build the self-contained chunk texts for a rule, in order. Pure
     * function -- no persistence.
     *
     * @return string[]
     */
    public function buildChunkTexts(CompanyRule $rule): array
    {
        $header = "{$rule->rule_code} — {$rule->title}";

        $context = [];
        if ($rule->category) {
            $context[] = "Category: {$rule->category->code} - {$rule->category->name}";
        }
        if ($rule->section) {
            $context[] = "Section: {$rule->section}";
        }

        $body = trim($rule->rule_text ?? '');

        if ($body === '') {
            return [];
        }

        $full = $header."\n\n".implode("\n", $context)."\n\n".$body;

        if (mb_strlen($full) <= self::MAX_CHUNK_CHARS) {
            return [$full];
        }

        // Safety split: break the body on paragraph boundaries and greedily
        // pack them into chunks under the limit, repeating the header on
        // each chunk so every piece stays self-contained on its own.
        $paragraphs = preg_split('/\n{2,}/', $body) ?: [$body];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            if (mb_strlen($header."\n\n".implode("\n", $context)."\n\n".$candidate) > self::MAX_CHUNK_CHARS && $current !== '') {
                $chunks[] = $header."\n\n".implode("\n", $context)."\n\n".$current;
                $current = $paragraph;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $header."\n\n".implode("\n", $context)."\n\n".$current;
        }

        return $chunks;
    }

    /**
     * Sync a rule's chunks in rule_chunks. Existing chunk rows are updated
     * in place (by chunk_index) when their content is unchanged so their id
     * -- and therefore their Qdrant point id -- stays stable; only chunks
     * whose text actually changed are marked 'pending' for re-embedding.
     * Trailing rows left over from a previously longer rule are removed.
     *
     * @return array{chunks: Collection<int, RuleChunk>, changed_ids: int[], created_ids: int[], updated_ids: int[], deleted_ids: int[]}
     */
    public function sync(CompanyRule $rule): array
    {
        $texts = $this->buildChunkTexts($rule);

        $existing = $rule->chunks()->orderBy('chunk_index')->get()->keyBy('chunk_index');

        $result = collect();
        $changedIds = [];
        $createdIds = [];
        $updatedIds = [];

        foreach ($texts as $index => $text) {
            $hash = hash('sha256', $text);
            /** @var RuleChunk|null $row */
            $row = $existing->get($index);

            if ($row && ($row->metadata['content_hash'] ?? null) === $hash) {
                $result->push($row);

                continue;
            }

            $isNew = $row === null;

            $row = RuleChunk::updateOrCreate(
                ['company_rule_id' => $rule->id, 'chunk_index' => $index],
                [
                    'chunk_text' => $text,
                    'token_count' => (int) ceil(mb_strlen($text) / 4),
                    'embedding_status' => 'pending',
                    'external_vector_id' => null,
                    'metadata' => ['content_hash' => $hash],
                ]
            );

            $result->push($row);
            $changedIds[] = $row->id;
            $isNew ? $createdIds[] = $row->id : $updatedIds[] = $row->id;
        }

        // Trailing chunks left over from a rule that used to be longer.
        $deletedIds = $existing->keys()
            ->filter(fn ($index) => $index >= count($texts))
            ->map(fn ($index) => $existing[$index]->id)
            ->values()
            ->all();

        if (! empty($deletedIds)) {
            RuleChunk::whereIn('id', $deletedIds)->delete();
        }

        return [
            'chunks' => $result,
            'changed_ids' => $changedIds,
            'created_ids' => $createdIds,
            'updated_ids' => $updatedIds,
            'deleted_ids' => $deletedIds,
        ];
    }
}
