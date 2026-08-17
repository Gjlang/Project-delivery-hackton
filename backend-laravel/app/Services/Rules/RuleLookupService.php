<?php

namespace App\Services\Rules;

use Illuminate\Support\Str;

/**
 * Replaces the retired Qdrant/embedding-based RuleRetrievalService now that
 * rules live in plain per-category tables (business_rules, company_policies,
 * employee_rules, security_compliance, technical_standards,
 * approval_governance -- see config/knowledge_rules.php) with no vector
 * index at all. There is no semantic search anymore: this does a simple
 * keyword-overlap ranking against title/rule_text within the requested
 * category prefixes, which is enough to keep "decision-by-decision"
 * retrieval meaningfully different per decision instead of always returning
 * the same first N rows.
 */
class RuleLookupService
{
    private const DEFAULT_LIMIT = 5;

    /**
     * @param  string[]  $categoryPrefixes  Category codes, e.g. ['BR'] or ['SC','TS']. Empty = search every category.
     * @return array{query: string, results: array<int, array<string, mixed>>}
     */
    public function search(string $query, array $categoryPrefixes = [], ?int $limit = null): array
    {
        $limit = $limit ?: self::DEFAULT_LIMIT;
        $categories = config('knowledge_rules');
        $prefixes = empty($categoryPrefixes) ? array_keys($categories) : array_intersect($categoryPrefixes, array_keys($categories));

        $keywords = $this->keywords($query);

        $scored = [];
        foreach ($prefixes as $prefix) {
            $model = $categories[$prefix]['model'];

            foreach ($model::orderBy('sort_order')->get() as $row) {
                $score = $this->score($keywords, $row->title, $row->rule_text);

                $scored[] = [
                    'rule_id' => $row->id,
                    'rule_code' => $row->rule_code,
                    'category' => $prefix,
                    'title' => $row->title,
                    'similarity_score' => $score,
                    'sort_order' => $row->sort_order,
                    'rule' => [
                        'rule_text' => $row->rule_text,
                        'applicable_condition' => null,
                    ],
                ];
            }
        }

        // Highest keyword overlap first; ties broken by the row's own
        // curated ordering so an empty/no-match query still returns a
        // sensible, stable default set instead of arbitrary DB order.
        usort($scored, fn ($a, $b) => $b['similarity_score'] <=> $a['similarity_score'] ?: $a['sort_order'] <=> $b['sort_order']);

        return ['query' => $query, 'results' => array_slice($scored, 0, $limit)];
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

    /**
     * Normalized 0..1 overlap score: fraction of query keywords found in the
     * rule's title/body, with a title match weighted higher than a body-only
     * match. Not a real relevance ranking (no idf/embeddings) -- just enough
     * to differentiate "which rules are actually about this decision" from
     * "everything else in the category".
     */
    private function score(array $keywords, string $title, string $ruleText): float
    {
        if (empty($keywords)) {
            return 0.0;
        }

        $titleLower = strtolower($title);
        $bodyLower = strtolower($ruleText);

        $hits = 0.0;
        foreach ($keywords as $keyword) {
            if (Str::contains($titleLower, $keyword)) {
                $hits += 1.0;
            } elseif (Str::contains($bodyLower, $keyword)) {
                $hits += 0.5;
            }
        }

        return round(min($hits / count($keywords), 1.0), 4);
    }
}
