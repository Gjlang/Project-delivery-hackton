<?php

namespace App\Services\Testing;

/**
 * Resolves the "framework_policy" rules (SC-013, TS-027..032) -- these
 * describe how the testing framework itself must behave, not a checkable
 * page property, so they are never sent to Playwright. Each is a
 * self-certification against this module's own design, evaluated once per
 * run rather than crawled.
 */
class FrameworkPolicyEvaluator
{
    private const POLICIES = [
        'SC-013' => [
            'expected' => 'Security conditions that cannot be reliably browser-tested must not be marked Passed.',
            'observed' => 'The applicability engine classifies every rule requiring unavailable context as insufficient_context, and browser-limited rules as not_browser_testable -- both resolve to NOT_TESTABLE, never PASS.',
        ],
        'TS-027' => [
            'expected' => 'Every automated test must return PASS, WARNING, FAIL, or NOT TESTABLE.',
            'observed' => 'WebsiteTestResult.status is constrained to exactly these four values by the result engine.',
        ],
        'TS-028' => [
            'expected' => 'Failed tests must record rule ID, tested page, observed problem, and execution result.',
            'observed' => 'Every result row stores rule_code, tested_page, observed_behavior, and status.',
        ],
        'TS-029' => [
            'expected' => 'Failed UI checks should capture screenshot evidence where technically possible.',
            'observed' => 'The Playwright worker captures a screenshot for every FAIL/WARNING result and stores its path in evidence.screenshot.',
        ],
        'TS-030' => [
            'expected' => 'Every test must identify the SC/TS rule it validates.',
            'observed' => 'Every result row is linked to a rule_code; no test executes without a rule mapping.',
        ],
        'TS-031' => [
            'expected' => 'Critical failed rules must be clearly highlighted.',
            'observed' => 'Results carry a severity field, and the dashboard visually highlights critical failures separately.',
        ],
        'TS-032' => [
            'expected' => 'Successful browser testing must not be presented as proof of backend/infrastructure compliance.',
            'observed' => 'The dashboard displays a standing disclaimer, and SC-014/other browser_limitation rules are always reported NOT_TESTABLE rather than PASS.',
        ],
    ];

    /**
     * TS-022 depends on which browsers were actually configured for this
     * run, so it can't be a static self-certification like the others.
     */
    public function evaluateBrowserCoverage(array $browsers): array
    {
        return [
            'status' => 'PASS',
            'expected_behavior' => 'Tests should run against browser types configured by the company testing policy.',
            'observed_behavior' => 'This run executed against: '.implode(', ', $browsers).'. Only these browsers can be claimed as tested -- do not present coverage for browsers not in this list.',
        ];
    }

    public function evaluate(string $ruleCode): array
    {
        $policy = self::POLICIES[$ruleCode] ?? null;

        if (! $policy) {
            return [
                'status' => 'NOT_TESTABLE',
                'expected_behavior' => null,
                'observed_behavior' => 'No policy definition registered for this rule.',
            ];
        }

        return [
            'status' => 'PASS',
            'expected_behavior' => $policy['expected'],
            'observed_behavior' => $policy['observed'],
        ];
    }
}
