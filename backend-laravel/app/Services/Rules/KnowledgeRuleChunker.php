<?php

namespace App\Services\Rules;

class KnowledgeRuleChunker
{
    public function chunk(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        $grouped = [];
        $currentSection = null;
        $current = null;
        $previousEndedWithColon = false;

        foreach ($lines as $rawLine) {
            $line = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $rawLine));

            if ($line === '') {
                continue;
            }

            if (preg_match('/^[•\-\*→]?\s*([A-Z]{2,3})-(\d{2,4})\s*[—\-:]\s*(.+)$/u', $line, $m)) {
                if ($current !== null) {
                    $this->flush($grouped, $current);
                }

                $current = [
                    'prefix' => $m[1],
                    'code' => $m[1].'-'.$m[2],
                    'title' => trim($m[3]),
                    'section' => $currentSection,
                    'body' => [],
                ];
                $previousEndedWithColon = false;

                continue;
            }

            if (! $previousEndedWithColon && $this->isHeading($line)) {
                $currentSection = $line;
                $previousEndedWithColon = false;

                continue;
            }

            $previousEndedWithColon = str_ends_with($line, ':');

            if ($current !== null) {
                $current['body'][] = ltrim($line, "•-*→\t ");
            }
        }

        if ($current !== null) {
            $this->flush($grouped, $current);
        }

        return $grouped;
    }

    private function flush(array &$grouped, array $current): void
    {
        $prefix = $current['prefix'];

        $grouped[$prefix][] = [
            'code' => $current['code'],
            'title' => $current['title'],
            'section' => $current['section'],
            'text' => trim(implode("\n", $current['body'])),
            'sort_order' => count($grouped[$prefix] ?? []),
        ];
    }

    private function isHeading(string $line): bool
    {
        if (preg_match('/^[•\-\*→\d]/u', $line)) {
            return false;
        }

        if (preg_match('/[.!?:]$/u', $line)) {
            return false;
        }

        if (! preg_match('/^[A-Z]/u', $line)) {
            return false;
        }

        $wordCount = str_word_count($line);

        return $wordCount >= 1 && $wordCount <= 8;
    }
}
