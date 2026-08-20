<?php

namespace App\Services\Testing;

use App\Models\Project;
use App\Models\SecurityComplianceRule;
use App\Models\TechnicalStandard;
use App\Models\WebsiteTestResult;
use App\Models\WebsiteTestRun;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates a full website test run: loads every SC/TS rule from the
 * security_compliance and technical_standards tables, determines
 * applicability, executes applicable checks through the Playwright worker,
 * and persists every result.
 */
class WebsiteTestingService
{
    private const CREDENTIAL_KEYS = ['valid_credentials', 'invalid_credentials', 'role_accounts'];

    public function __construct(
        private readonly RuleApplicabilityEngine $applicability,
        private readonly FrameworkPolicyEvaluator $policyEvaluator,
        private readonly WebsiteTestFeedbackService $feedback,
        private readonly PlaywrightWorkerRunner $worker,
    ) {
    }

    public function run(Project $project, string $websiteUrl, array $testContext, array $browserConfig, int $userId): WebsiteTestRun
    {
        $browsers = $browserConfig['browsers'] ?? ['chromium'];
        $timeoutMs = $browserConfig['timeout_ms'] ?? 15000;

        $run = WebsiteTestRun::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'website_url' => $websiteUrl,
            'status' => 'running',
            'browser_configuration' => ['browsers' => $browsers, 'timeout_ms' => $timeoutMs],
            'test_context_snapshot' => $this->redact($testContext),
            'started_at' => now(),
            'created_by' => $userId,
        ]);

        Log::info('Website test run started', ['run_id' => $run->id, 'project_id' => $project->id, 'website_url' => $websiteUrl]);

        $rules = SecurityComplianceRule::where('created_by', $userId)->get()
            ->concat(TechnicalStandard::where('created_by', $userId)->get());

        $run->update(['total_rules' => $rules->count()]);

        $queue = [];
        foreach ($rules as $rule) {
            $entry = config("testing_rules.{$rule->rule_code}");
            $type = $entry['type'] ?? null;

            if ($type === 'framework_policy') {
                $this->savePolicyResult($run, $rule, $browsers);

                continue;
            }

            $decision = $this->applicability->evaluate($rule->rule_code, $testContext);

            if ($decision['applicability_status'] !== 'applicable') {
                $this->saveSkippedResult($run, $rule, $decision);

                continue;
            }

            $queue[] = ['rule_code' => $rule->rule_code, 'handler' => $entry['handler']];
        }

        if (! empty($queue)) {
            $this->executeQueue($run, $rules->keyBy('rule_code'), $queue, $websiteUrl, $testContext, $browsers, $timeoutMs);
        }

        $this->finalizeRun($run);

        Log::info('Website test run completed', ['run_id' => $run->id, 'summary' => $run->fresh()->only(['passed', 'warnings', 'failed', 'not_testable'])]);

        return $run->fresh();
    }

    private function savePolicyResult(WebsiteTestRun $run, $rule, array $browsers): void
    {
        $policy = $rule->rule_code === 'TS-022'
            ? $this->policyEvaluator->evaluateBrowserCoverage($browsers)
            : $this->policyEvaluator->evaluate($rule->rule_code);
        $entry = config("testing_rules.{$rule->rule_code}");

        $narration = $this->narrate($rule->rule_code, $policy['status'], $rule->title ?? null, null, $policy['expected_behavior'], $policy['observed_behavior']);

        WebsiteTestResult::create([
            'website_test_run_id' => $run->id,
            'rule_code' => $rule->rule_code,
            'category' => substr($rule->rule_code, 0, 2),
            'applicability_status' => 'policy_rule',
            'status' => $policy['status'],
            'expected_behavior' => $policy['expected_behavior'],
            'observed_behavior' => $policy['observed_behavior'],
            'steps' => $narration['steps'],
            'reason' => $narration['reason'],
            'severity' => $entry['severity'] ?? null,
        ]);
    }

    private function saveSkippedResult(WebsiteTestRun $run, $rule, array $decision): void
    {
        $entry = config("testing_rules.{$rule->rule_code}");

        $narration = $this->narrate($rule->rule_code, $decision['status'], $rule->title ?? null, null, null, $decision['reason']);

        WebsiteTestResult::create([
            'website_test_run_id' => $run->id,
            'rule_code' => $rule->rule_code,
            'category' => substr($rule->rule_code, 0, 2),
            'applicability_status' => $decision['applicability_status'],
            'status' => $decision['status'],
            'observed_behavior' => $decision['reason'],
            'steps' => $narration['steps'],
            'reason' => $narration['reason'],
            'severity' => $entry['severity'] ?? null,
        ]);
    }

    private function executeQueue(WebsiteTestRun $run, $rulesByCode, array $queue, string $websiteUrl, array $testContext, array $browsers, int $timeoutMs): void
    {
        $screenshotDir = storage_path("app/testing/project_{$run->project_id}/run_{$run->id}");

        $input = [
            'test_run_id' => $run->id,
            'project_id' => $run->project_id,
            'website_url' => $websiteUrl,
            'browsers' => $browsers,
            'timeout_ms' => $timeoutMs,
            'screenshot_dir' => $screenshotDir,
            // Credentials go to the worker in-memory only -- never persisted
            // (test_context_snapshot on the run row is redacted separately).
            'test_context' => $testContext,
            'rules' => $queue,
        ];

        $output = $this->worker->run($input);

        if ($output === null) {
            foreach ($queue as $task) {
                $rule = $rulesByCode->get($task['rule_code']);
                $observed = 'The Playwright worker failed to execute or produced no output.';
                $narration = $this->narrate($task['rule_code'], 'NOT_TESTABLE', $rule->title ?? null, null, null, $observed);

                WebsiteTestResult::create([
                    'website_test_run_id' => $run->id,
                    'rule_code' => $task['rule_code'],
                    'category' => substr($task['rule_code'], 0, 2),
                    'applicability_status' => 'applicable',
                    'status' => 'NOT_TESTABLE',
                    'observed_behavior' => $observed,
                    'steps' => $narration['steps'],
                    'reason' => $narration['reason'],
                ]);
            }

            return;
        }

        foreach ($output as $result) {
            $rule = $rulesByCode->get($result['rule_code']);
            $applicability = ($result['applicability_override'] ?? null) === 'not_applicable' ? 'not_applicable' : 'applicable';

            $narration = $this->narrate(
                $result['rule_code'],
                $result['status'],
                $rule->title ?? null,
                $result['tested_page'] ?? null,
                $result['expected'] ?? null,
                $result['observed'] ?? null,
                $result['evidence'] ?? [],
            );

            $testResult = WebsiteTestResult::create([
                'website_test_run_id' => $run->id,
                'rule_code' => $result['rule_code'],
                'category' => substr($result['rule_code'], 0, 2),
                'applicability_status' => $applicability,
                'status' => $result['status'],
                'tested_page' => $result['tested_page'] ?? null,
                'expected_behavior' => $result['expected'] ?? null,
                'observed_behavior' => $result['observed'] ?? null,
                'steps' => $narration['steps'],
                'reason' => $narration['reason'],
                'severity' => config("testing_rules.{$result['rule_code']}.severity"),
                'execution_duration_ms' => $result['duration_ms'] ?? null,
                'evidence' => $this->relativizeEvidence($result['evidence'] ?? [], $screenshotDir),
            ]);

            if (in_array($testResult->status, ['FAIL', 'WARNING'], true) && $rule) {
                $this->feedback->generate($testResult, $rule);
            }
        }
    }

    /**
     * Deterministic per-result narration: an ordered list of what was
     * checked ("steps") and a one-line explanation of why the result came
     * out the way it did ("reason"). Built from data every call site already
     * has (rule code/title, expected vs. observed) -- not an LLM call, so it
     * always populates, even for PASS results (ai_explanation is only
     * generated for FAIL/WARNING, and is explicitly supplemental commentary
     * on top of this, not a replacement for it).
     *
     * @return array{steps: array<int, string>, reason: string}
     */
    private function narrate(
        string $ruleCode,
        string $status,
        ?string $ruleTitle,
        ?string $testedPage,
        ?string $expected,
        ?string $observed,
        array $evidence = [],
    ): array {
        $steps = [];

        if ($testedPage) {
            $browser = $evidence['browser'] ?? null;
            $steps[] = $browser ? "Loaded {$testedPage} in {$browser}." : "Loaded {$testedPage}.";
        }

        $steps[] = $ruleTitle ? "Checked against {$ruleCode}: {$ruleTitle}." : "Checked against {$ruleCode}.";

        if ($expected) {
            $steps[] = "Expected: {$expected}";
        }

        if ($observed) {
            $steps[] = "Observed: {$observed}";
        }

        $reason = match ($status) {
            'PASS' => "The website satisfied {$ruleCode}".($observed ? " -- {$observed}" : '.'),
            'FAIL' => "The website did not satisfy {$ruleCode}."
                .($expected && $observed ? " Expected {$expected}, but observed {$observed}." : ''),
            'WARNING' => "The website partially satisfied {$ruleCode} and needs review."
                .($observed ? " {$observed}" : ''),
            'NOT_TESTABLE' => "{$ruleCode} could not be verified through browser automation."
                .($observed ? " {$observed}" : ''),
            default => $observed ?? '',
        };

        return ['steps' => $steps, 'reason' => $reason];
    }

    /** Store screenshot paths relative to Laravel storage, not absolute filesystem paths. */
    private function relativizeEvidence(array $evidence, string $screenshotDir): array
    {
        if (! empty($evidence['screenshot']) && str_starts_with($evidence['screenshot'], $screenshotDir)) {
            $evidence['screenshot'] = ltrim(str_replace($screenshotDir, '', $evidence['screenshot']), '/\\');
        }

        return $evidence;
    }

    private function finalizeRun(WebsiteTestRun $run): void
    {
        $results = $run->results()->get();

        $run->update([
            'status' => 'completed',
            'completed_at' => now(),
            'executed_tests' => $results->count(),
            'passed' => $results->where('status', 'PASS')->count(),
            'warnings' => $results->where('status', 'WARNING')->count(),
            'failed' => $results->where('status', 'FAIL')->count(),
            'not_testable' => $results->where('status', 'NOT_TESTABLE')->count(),
        ]);
    }

    /**
     * Credentials must never reach the database, logs, or any external
     * service -- only the worker sees them, in-memory, for the duration of
     * the run.
     */
    private function redact(array $testContext): array
    {
        $redacted = $testContext;

        foreach (self::CREDENTIAL_KEYS as $key) {
            if (isset($redacted[$key])) {
                $redacted[$key] = is_array($redacted[$key]) && array_is_list($redacted[$key])
                    ? array_fill(0, count($redacted[$key]), '[REDACTED]')
                    : '[REDACTED]';
            }
        }

        return $redacted;
    }
}
