<?php

namespace App\Services\Testing;

/**
 * Decides, per rule, whether a real Playwright check should run at all --
 * separate from the eventual PASS/WARNING/FAIL/NOT_TESTABLE test result.
 * Every active SC/TS rule is classified here; none are silently skipped for
 * failing to appear in a Top-K semantic search.
 */
class RuleApplicabilityEngine
{
    /**
     * @return array{applicability_status: string, status: ?string, reason: ?string}
     */
    public function evaluate(string $ruleCode, array $testContext): array
    {
        $entry = config("testing_rules.{$ruleCode}");

        if (! $entry) {
            return [
                'applicability_status' => 'not_browser_testable',
                'status' => 'NOT_TESTABLE',
                'reason' => 'No handler is registered for this rule code.',
            ];
        }

        return match ($entry['type']) {
            'browser_limitation' => [
                'applicability_status' => 'not_browser_testable',
                'status' => 'NOT_TESTABLE',
                'reason' => 'This rule describes a control that cannot be verified from the browser (backend/infrastructure).',
            ],
            'framework_policy' => [
                'applicability_status' => 'policy_rule',
                'status' => null, // resolved by FrameworkPolicyEvaluator, not here
                'reason' => null,
            ],
            'browser_automated' => [
                'applicability_status' => 'applicable',
                'status' => null,
                'reason' => null,
            ],
            'context_dependent' => $this->evaluateContextDependent($entry['requires'], $testContext),
            default => [
                'applicability_status' => 'not_browser_testable',
                'status' => 'NOT_TESTABLE',
                'reason' => "Unknown handler type [{$entry['type']}].",
            ],
        };
    }

    private function evaluateContextDependent(array $requiredKeys, array $testContext): array
    {
        $missing = array_filter($requiredKeys, fn ($key) => empty($testContext[$key]));

        if (! empty($missing)) {
            return [
                'applicability_status' => 'insufficient_context',
                'status' => 'NOT_TESTABLE',
                'reason' => 'Missing required testing context: '.implode(', ', $missing).'.',
            ];
        }

        return ['applicability_status' => 'applicable', 'status' => null, 'reason' => null];
    }
}
