<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class DocumentParser
{
    /**
     * Extract raw text from a stored file, dispatching by extension.
     */
    public function extractText(string $absolutePath, string $extension): string
    {
        $extension = strtolower($extension);

        $text = match ($extension) {
            'pdf' => $this->extractFromPdf($absolutePath),
            'docx' => $this->extractFromDocx($absolutePath),
            'doc' => $this->extractPrintableStrings($absolutePath),
            'txt', 'md' => file_get_contents($absolutePath) ?: '',
            default => '',
        };

        return trim($text);
    }

    private function extractFromPdf(string $absolutePath): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($absolutePath);

        return $pdf->getText();
    }

    private function extractFromDocx(string $absolutePath): string
    {
        $zip = new ZipArchive();

        if ($zip->open($absolutePath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $xml) {
            return '';
        }

        $xml = str_replace(['</w:p>', '<w:tab/>', '<w:br/>'], ["\n", "\t", "\n"], $xml);

        return html_entity_decode(strip_tags($xml));
    }

    /**
     * Best-effort text recovery for legacy binary .doc files (no reliable
     * parser without a heavy external dependency): pulls out runs of
     * printable characters, which is enough to keyword/rule-match against.
     */
    private function extractPrintableStrings(string $absolutePath): string
    {
        $raw = file_get_contents($absolutePath) ?: '';
        preg_match_all('/[\x20-\x7E]{5,}/', $raw, $matches);

        return implode("\n", $matches[0] ?? []);
    }

    /**
     * Split text into candidate rule/sentence statements: one per line for
     * bullet-style documents, further split on sentence boundaries for long
     * paragraph lines.
     *
     * @return string[]
     */
    public function statements(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $statements = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $line = ltrim($line, "-•*→\t ");

            if ($line === '') {
                continue;
            }

            if (mb_strlen($line) > 220) {
                $parts = preg_split('/(?<=[.!?])\s+/', $line) ?: [$line];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $statements[] = $part;
                    }
                }
            } else {
                $statements[] = $line;
            }
        }

        return $statements;
    }

    /**
     * Word-by-word scan of $text for every keyword in $words, returning only
     * the keywords that actually occur, with their occurrence counts.
     *
     * @param  string[]  $words
     * @return array<string, int>
     */
    public function countKeywordHits(string $text, array $words): array
    {
        $hits = [];

        foreach ($words as $word) {
            $count = preg_match_all('/\b'.preg_quote($word, '/').'\b/i', $text);
            if ($count > 0) {
                $hits[$word] = $count;
            }
        }

        arsort($hits);

        return $hits;
    }

    /**
     * Score every statement by how many distinct rule-trigger words it
     * contains (plus a bonus for numeric thresholds like "14 days" or
     * "2 signatures"), and return the highest scoring statements as the
     * document's "found rules".
     *
     * @param  string[]  $triggerWords
     * @return string[]
     */
    public function findRules(array $statements, array $triggerWords, ?int $limit = null): array
    {
        $scored = [];

        foreach ($statements as $statement) {
            if (mb_strlen($statement) < 8) {
                continue;
            }

            $score = 0;
            foreach ($triggerWords as $word) {
                if (preg_match('/\b'.preg_quote($word, '/').'\b/i', $statement)) {
                    $score++;
                }
            }

            if ($score === 0) {
                continue;
            }

            if (preg_match('/\d/', $statement)) {
                $score++;
            }

            $key = mb_strtolower($statement);
            if (! isset($scored[$key]) || $scored[$key]['score'] < $score) {
                $scored[$key] = [
                    'text' => mb_strlen($statement) > 240 ? mb_substr($statement, 0, 237).'...' : $statement,
                    'score' => $score,
                ];
            }
        }

        uasort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $results = array_column($scored, 'text');

        return $limit === null ? $results : array_slice($results, 0, $limit);
    }

    /**
     * Full analysis pipeline: extract, then detect word/section/rule
     * signals for a given category's keyword set + the shared rule triggers.
     */
    public function analyze(string $text, array $categoryKeywords, array $ruleTriggerWords): array
    {
        $statements = $this->statements($text);

        $wordCount = count(preg_split('/\s+/', trim($text)) ?: []);
        if ($text === '') {
            $wordCount = 0;
        }

        $sections = count(array_filter($statements, fn ($s) => str_word_count($s) >= 5));

        $summaryStatements = array_slice(array_filter($statements, fn ($s) => mb_strlen($s) >= 15), 0, 2);
        $summary = implode(' ', $summaryStatements);
        if (mb_strlen($summary) > 300) {
            $summary = mb_substr($summary, 0, 297).'...';
        }

        $keywordHits = $this->countKeywordHits($text, array_merge($categoryKeywords, $ruleTriggerWords));

        $rules = $this->findRules($statements, $ruleTriggerWords);

        return [
            'summary' => $summary,
            'sections' => $sections,
            'rules' => $rules,
            'keyword_hits' => $keywordHits,
            'word_count' => $wordCount,
            'text' => $text,
        ];
    }
}
