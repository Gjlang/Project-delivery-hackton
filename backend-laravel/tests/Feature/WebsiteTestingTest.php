<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\SecurityComplianceRule;
use App\Models\TechnicalStandard;
use App\Models\User;
use App\Models\WebsiteTestResult;
use App\Models\WebsiteTestRun;
use App\Services\LLM\LLMException;
use App\Services\LLM\LLMService;
use App\Services\Testing\PlaywrightWorkerRunner;
use App\Services\Testing\WebsiteTestingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteTestingTest extends TestCase
{
    use RefreshDatabase;

    private function seedAllRules(Company $company, int $userId): void
    {
        foreach (config('testing_rules') as $ruleCode => $entry) {
            $categoryCode = substr($ruleCode, 0, 2);
            $model = $categoryCode === 'SC' ? SecurityComplianceRule::class : TechnicalStandard::class;

            $model::create([
                'created_by' => $userId,
                'rule_code' => $ruleCode,
                'title' => "{$ruleCode} title",
                'rule_text' => "{$ruleCode} text",
            ]);
        }
    }

    private int $userId;

    private function makeProject(?Company $company = null): Project
    {
        $company ??= Company::create(['name' => 'Test Co']);
        $this->userId = User::factory()->create(['company_id' => $company->id])->id;
        $this->seedAllRules($company, $this->userId);

        return Project::create([
            'company_id' => $company->id,
            'created_by' => $this->userId,
            'name' => 'Test Project',
            'status' => 'draft',
        ]);
    }

    /** Fake worker: returns PASS for every queued rule unless a per-rule override is given. */
    private function bindFakeWorker(array $overrides = [], bool $returnNull = false): void
    {
        $this->app->bind(PlaywrightWorkerRunner::class, function () use ($overrides, $returnNull) {
            return new class($overrides, $returnNull) implements PlaywrightWorkerRunner
            {
                public function __construct(private array $overrides, private bool $returnNull) {}

                public function run(array $input): ?array
                {
                    if ($this->returnNull) {
                        return null;
                    }

                    $results = [];
                    foreach ($input['rules'] as $task) {
                        $override = $this->overrides[$task['rule_code']] ?? [];
                        $results[] = array_merge([
                            'rule_code' => $task['rule_code'],
                            'status' => 'PASS',
                            'tested_page' => $input['website_url'],
                            'expected' => 'expected behaviour',
                            'observed' => 'observed behaviour',
                            'evidence' => ['timestamp' => now()->toISOString()],
                            'duration_ms' => 10,
                        ], $override);
                    }

                    return $results;
                }
            };
        });
    }

    private function bindFakeLlm(): void
    {
        $this->app->bind(LLMService::class, function () {
            return new class implements LLMService
            {
                public function generate(string $prompt, bool $jsonMode = true): string
                {
                    return json_encode(['explanation' => 'Test explanation.', 'impact' => 'Test impact.', 'recommendation' => 'Test recommendation.']);
                }
            };
        });
    }

    private function bindFailingLlm(): void
    {
        $this->app->bind(LLMService::class, function () {
            return new class implements LLMService
            {
                public function generate(string $prompt, bool $jsonMode = true): string
                {
                    throw new LLMException('LLM unavailable.');
                }
            };
        });
    }

    // ---- Rule registry sanity ----

    public function test_rule_registry_covers_all_46_sc_ts_rule_codes(): void
    {
        $registry = config('testing_rules');
        $scCodes = array_map(fn ($n) => 'SC-'.str_pad($n, 3, '0', STR_PAD_LEFT), range(1, 14));
        $tsCodes = array_map(fn ($n) => 'TS-'.str_pad($n, 3, '0', STR_PAD_LEFT), range(1, 32));

        foreach ([...$scCodes, ...$tsCodes] as $code) {
            $this->assertArrayHasKey($code, $registry, "Missing registry entry for {$code}");
        }

        $this->assertCount(46, $registry);
    }

    // ---- Run creation & full rule coverage ----

    public function test_run_creation_processes_every_active_sc_ts_rule(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals(46, $run->total_rules);
        $this->assertEquals(46, $run->executed_tests);
        $this->assertEquals(46, WebsiteTestResult::where('website_test_run_id', $run->id)->count());
    }

    // ---- Applicability ----

    public function test_context_dependent_rule_without_context_is_insufficient_context(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'SC-003')->first();
        $this->assertEquals('insufficient_context', $result->applicability_status);
        $this->assertEquals('NOT_TESTABLE', $result->status);
    }

    public function test_context_dependent_rule_with_context_is_applicable(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', ['protected_routes' => ['/dashboard']], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'SC-003')->first();
        $this->assertEquals('applicable', $result->applicability_status);
        $this->assertEquals('PASS', $result->status); // fake worker returns PASS
    }

    public function test_browser_limitation_rule_is_always_not_testable(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'SC-014')->first();
        $this->assertEquals('not_browser_testable', $result->applicability_status);
        $this->assertEquals('NOT_TESTABLE', $result->status);
    }

    public function test_framework_policy_rules_never_reach_the_worker_and_pass(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'TS-027')->first();
        $this->assertEquals('policy_rule', $result->applicability_status);
        $this->assertEquals('PASS', $result->status);
    }

    // ---- Result statuses ----

    public function test_fail_result_is_persisted(): void
    {
        $this->bindFakeWorker(['SC-001' => ['status' => 'FAIL', 'observed' => 'Not HTTPS.']]);
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'http://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'SC-001')->first();
        $this->assertEquals('FAIL', $result->status);
        $this->assertEquals(1, $run->fresh()->failed);
    }

    public function test_warning_result_is_persisted(): void
    {
        $this->bindFakeWorker(['SC-004' => ['status' => 'WARNING']]);
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', ['protected_routes' => ['/x']], ['browsers' => ['chromium']], $this->userId);

        $this->assertEquals(1, $run->fresh()->warnings);
    }

    public function test_not_testable_worker_result_is_persisted(): void
    {
        $this->bindFakeWorker(['TS-001' => ['status' => 'NOT_TESTABLE', 'observed' => 'Could not resolve host.']]);
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'TS-001')->first();
        $this->assertEquals('NOT_TESTABLE', $result->status);
    }

    // ---- Evidence / screenshots ----

    public function test_screenshot_evidence_path_is_relativized_and_stored(): void
    {
        $this->bindFakeWorker(['SC-001' => [
            'status' => 'FAIL',
            'evidence' => ['timestamp' => now()->toISOString(), 'screenshot' => storage_path('app/testing/project_1/run_1/SC-001.png')],
        ]]);
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'http://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'SC-001')->first();
        $this->assertEquals('SC-001.png', $result->evidence['screenshot']);
    }

    // ---- Worker failure isolation ----

    public function test_worker_returning_null_falls_back_to_not_testable_without_losing_the_run(): void
    {
        $this->bindFakeWorker(returnNull: true);
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $this->assertEquals('completed', $run->status);
        $applicableRuleResults = $run->results()->where('applicability_status', 'applicable')->get();
        $this->assertTrue($applicableRuleResults->every(fn ($r) => $r->status === 'NOT_TESTABLE'));
    }

    // ---- Summary calculation ----

    public function test_run_summary_counts_are_calculated_correctly(): void
    {
        $this->bindFakeWorker(['SC-001' => ['status' => 'FAIL'], 'SC-006' => ['status' => 'WARNING']]);
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'https://example.com', [], ['browsers' => ['chromium']], 1)->fresh();

        $this->assertEquals($run->passed + $run->warnings + $run->failed + $run->not_testable, $run->executed_tests);
        $this->assertEquals(1, $run->failed);
        $this->assertEquals(1, $run->warnings);
    }

    // ---- Retesting / history ----

    public function test_retesting_creates_a_new_run_and_preserves_history(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $service = app(WebsiteTestingService::class);

        $run1 = $service->run($project, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);
        $run2 = $service->run($project, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $this->assertNotEquals($run1->id, $run2->id);
        $this->assertEquals(2, WebsiteTestRun::where('project_id', $project->id)->count());
        $this->assertEquals($run2->id, $project->latestTestRun()->first()->id);
        $this->assertDatabaseHas('website_test_runs', ['id' => $run1->id]); // history preserved
    }

    // ---- Credential secrecy ----

    public function test_credentials_are_redacted_from_the_persisted_run_snapshot(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run(
            $project,
            'https://example.com',
            ['valid_credentials' => ['username' => 'admin', 'password' => 'super-secret-123']],
            ['browsers' => ['chromium']],
            1
        );

        $snapshot = $run->fresh()->test_context_snapshot;
        $this->assertEquals('[REDACTED]', $snapshot['valid_credentials']);
        $this->assertStringNotContainsString('super-secret-123', json_encode($snapshot));
    }

    // ---- LLM feedback failure isolation ----

    public function test_llm_feedback_failure_does_not_change_the_deterministic_result(): void
    {
        $this->bindFakeWorker(['SC-001' => ['status' => 'FAIL', 'observed' => 'Not HTTPS.']]);
        $this->bindFailingLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'http://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'SC-001')->first();
        $this->assertEquals('FAIL', $result->status); // untouched despite LLM failure
        $this->assertNotNull($result->ai_explanation); // deterministic fallback text still present
    }

    public function test_llm_feedback_is_generated_for_fail_results(): void
    {
        $this->bindFakeWorker(['SC-001' => ['status' => 'FAIL']]);
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($project, 'http://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $result = $run->results()->where('rule_code', 'SC-001')->first();
        $this->assertEquals('Test explanation.', $result->ai_explanation);
        $this->assertEquals('Test recommendation.', $result->ai_recommendation);
    }

    // ---- Company scoping / endpoint ----

    public function test_cross_company_project_cannot_be_tested(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $projectB = $this->makeProject(); // different company

        $response = $this->actingAs($userA)->postJson("/projects/{$projectB->id}/testing", ['website_url' => 'https://example.com']);

        $response->assertStatus(404);
    }

    public function test_cross_company_run_cannot_be_viewed(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $companyA = Company::create(['name' => 'Company A']);
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $projectB = $this->makeProject();
        $run = app(WebsiteTestingService::class)->run($projectB, 'https://example.com', [], ['browsers' => ['chromium']], $this->userId);

        $response = $this->actingAs($userA)->get("/projects/{$projectB->id}/testing/{$run->id}");
        $response->assertStatus(404);
    }

    public function test_invalid_website_url_is_rejected(): void
    {
        $project = $this->makeProject();
        $user = User::find($this->userId);

        $response = $this->actingAs($user)->post("/projects/{$project->id}/testing", ['website_url' => 'not-a-url']);

        $response->assertSessionHasErrors('website_url');
    }

    public function test_endpoint_runs_a_full_test_and_redirects_to_results(): void
    {
        $this->bindFakeWorker();
        $this->bindFakeLlm();

        $project = $this->makeProject();
        $user = User::find($this->userId);

        $response = $this->actingAs($user)->post("/projects/{$project->id}/testing", ['website_url' => 'https://example.com']);

        $run = WebsiteTestRun::where('project_id', $project->id)->first();
        $response->assertRedirect(route('projects.testing.show', [$project, $run]));
    }
}
