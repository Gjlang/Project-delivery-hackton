<?php

namespace App\Services\Assistant;

use App\Models\AssistantMemory;
use App\Models\AssistantThread;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Whole-app, per-user memory: one AssistantThread per user (created at
 * login), holding an append-only list of short durable facts
 * (AssistantMemory) any AI feature can read from and write to -- not owned
 * by project creation specifically, so a future AI feature can reuse the
 * same thread/memory tables without new infrastructure.
 *
 * No vector DB in this app (see RuleLookupService) -- recall() uses the same
 * keyword-overlap scoring approach rather than embeddings.
 */
class AssistantMemoryService
{
    private const MAX_MEMORY_LENGTH = 300;

    private const DEDUPE_WINDOW = 20;

    private const DEFAULT_RECALL_LIMIT = 5;

    public function ensureThreadForUser(User $user): AssistantThread
    {
        return AssistantThread::firstOrCreate(
            ['user_id' => $user->id],
            ['company_id' => $user->company_id]
        );
    }

    /**
     * Deterministic guards before persisting -- never trust the LLM output
     * blindly, mirrors ProjectCreationResponseValidator's placeholder
     * filtering and RequirementAnalysisValidator's schema-placeholder check.
     */
    public function remember(AssistantThread $thread, string $content, string $source): void
    {
        $content = trim($content);

        if ($content === '' || $this->isPlaceholder($content)) {
            return;
        }

        if (Str::length($content) > self::MAX_MEMORY_LENGTH) {
            $content = Str::limit($content, self::MAX_MEMORY_LENGTH, '');
        }

        $recent = $thread->memories()->latest('created_at')->take(self::DEDUPE_WINDOW)->pluck('content');

        $normalized = strtolower($content);
        $isDuplicate = $recent->contains(fn ($existing) => str_contains(strtolower($existing), $normalized) || str_contains($normalized, strtolower($existing)));

        if ($isDuplicate) {
            return;
        }

        AssistantMemory::create([
            'assistant_thread_id' => $thread->id,
            'content' => $content,
            'source' => $source,
        ]);
    }

    /**
     * @return string[]
     */
    public function recall(AssistantThread $thread, string $query, int $limit = self::DEFAULT_RECALL_LIMIT): array
    {
        $keywords = $this->keywords($query);
        $memories = $thread->memories()->latest('created_at')->get();

        if ($memories->isEmpty()) {
            return [];
        }

        $scored = $memories->map(fn ($memory) => [
            'content' => $memory->content,
            'score' => $this->score($keywords, $memory->content),
            'created_at' => $memory->created_at,
        ]);

        return $scored
            ->sort(function ($a, $b) {
                return $b['score'] <=> $a['score'] ?: $b['created_at'] <=> $a['created_at'];
            })
            ->take($limit)
            ->pluck('content')
            ->values()
            ->all();
    }

    private function isPlaceholder(string $value): bool
    {
        return in_array(strtolower($value), ['string', 'null', 'n/a', 'none', 'todo', 'no fact', 'no durable fact'], true);
    }

    /**
     * @return string[]
     */
    private function keywords(string $query): array
    {
        $words = preg_split('/[^a-z0-9]+/i', strtolower($query)) ?: [];
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'for', 'of', 'to', 'that', 'this', 'rules', 'rule'];

        return array_values(array_unique(array_filter($words, fn ($w) => strlen($w) > 2 && ! in_array($w, $stopwords, true))));
    }

    private function score(array $keywords, string $content): float
    {
        if (empty($keywords)) {
            return 0.0;
        }

        $contentLower = strtolower($content);
        $hits = 0.0;
        foreach ($keywords as $keyword) {
            if (Str::contains($contentLower, $keyword)) {
                $hits += 1.0;
            }
        }

        return round(min($hits / count($keywords), 1.0), 4);
    }
}
