<?php

namespace App\Services\Testing;

use App\Models\TestingResultRule;
use App\Models\TestingResultRuleCheck;
use App\Models\WebsiteTestRun;
use App\Services\LLM\LLMException;
use App\Services\LLM\LLMService;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates a project's "TR" (Testing Standards) rules against an already-
 * completed WebsiteTestRun's results -- the Phase 5 gate in
 * ProjectPhaseController requires every rule here to resolve to PASS or
 * NOT_APPLICABLE before Phase 5 can be marked done. Same
 * PASS/NEEDS_INFORMATION/FAIL/NOT_APPLICABLE vocabulary Python's
 * RuleValidationResult already uses, for consistency, but this runs
 * directly in Laravel the same way WebsiteTestFeedbackService does (no
 * Python round-trip needed).
 */
class TestingResultRuleValidationService
{
    private const VALID_STATUSES = ['PASS', 'NEEDS_INFORMATION', 'FAIL', 'NOT_APPLICABLE'];

    public function __construct(private readonly LLMService $llm)
    {
    }

    /**
     * @return \Illuminate\Support\Collection<int, TestingResultRuleCheck>
     */
    public function validate(WebsiteTestRun $run, int $userId): \Illuminate\Support\Collection
    {
        $rules = TestingResultRule::where('created_by', $userId)->orderBy('sort_order')->get();

        if ($rules->isEmpty()) {
            return collect();
        }

        $summary = $this->buildRunSummary($run);
        $results = $this->evaluate($rules, $summary);

        $checks = $rules->map(function (TestingResultRule $rule) use ($run, $results) {
            $result = $results[$rule->rule_code] ?? null;

            return TestingResultRuleCheck::create([
                'website_test_run_id' => $run->id,
                'rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'title' => $rule->title,
                'status' => $result['status'] ?? 'NEEDS_INFORMATION',
                'reason' => $result['reason'] ?? 'The validator did not return a result for this rule.',
            ]);
        });

        return $checks;
    }

    private function buildRunSummary(WebsiteTestRun $run): array
    {
        return [
            'website_url' => $run->website_url,
            'passed' => $run->passed,
            'warnings' => $run->warnings,
            'failed' => $run->failed,
            'not_testable' => $run->not_testable,
            'executed_tests' => $run->executed_tests,
            'results' => $run->results()->get(['rule_code', 'category', 'status', 'severity'])
                ->map(fn ($r) => [
                    'rule_code' => $r->rule_code,
                    'category' => $r->category,
                    'status' => $r->status,
                    'severity' => $r->severity,
                ])->all(),
        ];
    }

    /**
     * @return array<string, array{status: string, reason: string}> keyed by rule_code
     */
    private function evaluate(\Illuminate\Support\Collection $rules, array $summary): array
    {
        $prompt = $this->buildPrompt($rules, $summary);

        try {
            $raw = $this->llm->generate($prompt, jsonMode: true);
            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || ! isset($decoded['results']) || ! is_array($decoded['results'])) {
                throw new LLMException('Testing rule validator returned invalid JSON.');
            }

            $byCode = [];
            foreach ($decoded['results'] as $item) {
                $code = $item['rule_code'] ?? null;
                $status = strtoupper((string) ($item['status'] ?? ''));

                if (! $code || ! in_array($status, self::VALID_STATUSES, true)) {
                    continue;
                }

                $byCode[$code] = [
                    'status' => $status,
                    'reason' => is_string($item['reason'] ?? null) ? $item['reason'] : 'No reason given.',
                ];
            }

            return $byCode;
        } catch (\Throwable $e) {
            Log::warning('Testing standards validation failed, marking all rules NEEDS_INFORMATION', ['error' => $e->getMessage()]);

            return $rules->mapWithKeys(fn ($rule) => [
                $rule->rule_code => ['status' => 'NEEDS_INFORMATION', 'reason' => 'Validation failed: '.$e->getMessage()],
            ])->all();
        }
    }

    private function buildPrompt(\Illuminate\Support\Collection $rules, array $summary): string
    {
        $compactRules = $rules->map(fn ($r) => ['rule_code' => $r->rule_code, 'rule_text' => $r->rule_text])->values();
        $rulesJson = json_encode($compactRules, JSON_UNESCAPED_SLASHES);
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Evaluate a completed Playwright website test run against the supplied
company testing standards.

Important:
- Evaluate ONLY the supplied rules, using ONLY the supplied test run evidence.
- Do not invent evidence.
- PASS: the test run evidence satisfies the rule.
- NEEDS_INFORMATION: the rule may apply but the test run doesn't contain enough evidence to decide.
- FAIL: the test run evidence directly conflicts with the rule.
- NOT_APPLICABLE: the rule clearly does not apply to this test run.

TEST RUN SUMMARY:
{$summaryJson}

RULES:
{$rulesJson}

Return ONLY valid JSON of this exact shape:
{"results": [{"rule_code": "string", "status": "PASS|NEEDS_INFORMATION|FAIL|NOT_APPLICABLE", "reason": "string"}]}

Return the JSON now:
PROMPT;
    }
}
