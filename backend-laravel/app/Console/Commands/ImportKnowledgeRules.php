<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports a rulebook in the same markdown shape KnowledgeRuleChunker expects
 * (bold "**XX-000 — Title**" rule headers, "### " subsection headers) from a
 * file path -- for bulk-loading an authoritative rule set directly, without
 * going through the document-upload endpoint.
 */
class ImportKnowledgeRules extends Command
{
    protected $signature = 'knowledge-rules:import {path} {--replace : Delete all existing rows in every category table first}';

    protected $description = 'Import rules from a markdown rulebook file into the per-category rule tables';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $text = file_get_contents($path);
        $grouped = $this->parse($text);
        $categories = config('knowledge_rules');

        if ($this->option('replace')) {
            foreach ($categories as $meta) {
                $meta['model']::query()->delete();
            }
            $this->info('Cleared all existing rows in every category table.');
        }

        DB::transaction(function () use ($grouped, $categories) {
            foreach ($grouped as $prefix => $rules) {
                $meta = $categories[$prefix] ?? null;

                if ($meta === null) {
                    $this->warn("Skipping unknown category prefix [{$prefix}] ({$prefix}-* rules not in config/knowledge_rules.php).");

                    continue;
                }

                foreach ($rules as $rule) {
                    $meta['model']::updateOrCreate(
                        ['rule_code' => $rule['code']],
                        [
                            'section' => $rule['section'],
                            'title' => $rule['title'],
                            'rule_text' => $rule['text'],
                            'sort_order' => $rule['sort_order'],
                        ]
                    );
                }

                $this->info("{$prefix}: imported ".count($rules).' rules');
            }
        });

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, array{code: string, title: string, section: ?string, text: string, sort_order: int}>>
     */
    private function parse(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        $grouped = [];
        $currentSection = null;
        $current = null;

        $flush = function () use (&$grouped, &$current) {
            if ($current === null) {
                return;
            }

            $prefix = $current['prefix'];
            $grouped[$prefix][] = [
                'code' => $current['code'],
                'title' => $current['title'],
                'section' => $current['section'],
                'text' => trim(implode("\n", $current['body'])),
                'sort_order' => count($grouped[$prefix] ?? []),
            ];
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                if ($current !== null) {
                    $current['body'][] = '';
                }

                continue;
            }

            // Rule header, e.g. "**AG-001 — Project Manager Review**"
            if (preg_match('/^\*\*([A-Z]{2,3})-(\d{2,4})\s*[—\-:]\s*(.+?)\*\*$/u', $line, $m)) {
                $flush();

                $current = [
                    'prefix' => $m[1],
                    'code' => $m[1].'-'.$m[2],
                    'title' => trim($m[3]),
                    'section' => $currentSection,
                    'body' => [],
                ];

                continue;
            }

            // Subsection header, e.g. "### Project Planning Approval"
            if (preg_match('/^#{2,3}\s+(.+)$/u', $line, $m)) {
                // Top-level "## AG_Approval_and_Governance" / "# BR_Business_Rules"
                // markers double as category separators -- reset section, but
                // rule routing itself is driven by the rule code prefix, not
                // this heading, so we don't need to parse the slug.
                $flush();
                $current = null;
                $currentSection = str_starts_with($line, '###') ? trim($m[1]) : null;

                continue;
            }

            if ($current !== null) {
                $current['body'][] = trim($line, "*-• \t");
            }
        }

        $flush();

        // Trim accumulated blank-line placeholders down to single blank
        // lines between paragraphs instead of runs of them.
        foreach ($grouped as $prefix => $rules) {
            foreach ($rules as $i => $rule) {
                $grouped[$prefix][$i]['text'] = preg_replace('/\n{3,}/', "\n\n", $rule['text']);
            }
        }

        return $grouped;
    }
}
