<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\RequirementAnalysisRun;
use App\Models\User;
use App\Services\LLM\LLMException;
use App\Services\LLM\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RequirementAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(array $overrides = []): Project
    {
        $company = Company::create(['name' => 'Test Co']);

        return Project::create([
            'company_id' => $company->id,
            'name' => $overrides['name'] ?? 'Employee Management System',
            'business_objective' => array_key_exists('business_objective', $overrides) ? $overrides['business_objective'] : 'Reduce manual HR administration.',
            'description' => array_key_exists('description', $overrides) ? $overrides['description'] : 'Build a web-based system for employees, HR staff and managers.',
            'requirements_raw' => $overrides['requirements_raw'] ?? "- Login\n- Employee CRUD\n- Leave request\n- Leave approval\n- Admin dashboard\n- Email notification",
            'start_date' => array_key_exists('start_date', $overrides) ? $overrides['start_date'] : '2026-09-01',
            'status' => 'draft',
        ]);
    }

    private function fakeEmptyRuleRetrieval(): void
    {
        Http::fake([
            '*/api/embed' => Http::response(['model' => 'nomic-embed-text', 'embeddings' => [[0.1, 0.2, 0.3]]]),
            '*/collections/*/points/search' => Http::response(['result' => []]),
        ]);
    }

    private function bindLlmResponses(array $responses): void
    {
        $this->app->bind(LLMService::class, function () use ($responses) {
            return new class($responses) implements LLMService
            {
                private int $i = 0;

                public function __construct(private array $responses) {}

                public function generate(string $prompt, bool $jsonMode = true): string
                {
                    $response = $this->responses[$this->i] ?? end($this->responses);
                    $this->i++;

                    if ($response instanceof \Throwable) {
                        throw $response;
                    }

                    return $response;
                }
            };
        });
    }

    private function completeCannedJson(): string
    {
        return json_encode([
            'major_features' => [
                ['name' => 'Authentication', 'source_text' => 'Login'],
            ],
            'functional_requirements' => [
                ['name' => 'Employee Management', 'description' => 'Manage employee records', 'source_text' => 'Employee CRUD', 'priority' => null, 'mandatory' => false],
                ['name' => 'Two-Factor Authentication', 'description' => 'Extra login security', 'source_text' => 'Critical: two-factor authentication', 'priority' => 'critical', 'mandatory' => true],
            ],
            'non_functional_requirements' => [
                ['name' => 'Performance', 'description' => 'Pages should load quickly', 'source_text' => 'Fast performance', 'priority' => null, 'mandatory' => false],
            ],
            'business_constraints' => [],
            'integrations' => [
                ['name' => 'Email Service', 'purpose' => 'Send notifications', 'source_text' => 'Email notification'],
            ],
            'data_requirements' => [
                ['name' => 'Employee Data', 'description' => 'Persistent storage of employee information'],
            ],
            'user_roles' => ['Employee', 'HR Staff', 'Manager', 'Administrator'],
            'platforms' => ['Web'],
            'clarifications_required' => [],
            'assumptions' => [],
        ]);
    }

    // ---- Complete analysis ----

    public function test_complete_requirement_analysis(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertTrue($result['success']);
        $this->assertEquals('complete', $result['analysis_status']);
        $this->assertEquals(2, $result['summary']['functional_requirements']);
        $this->assertEquals(1, $result['summary']['non_functional_requirements']);
        $this->assertEquals(1, $result['summary']['integrations']);
        $this->assertEquals(4, $result['summary']['user_roles']);
        $this->assertEmpty($result['missing_required_information']);
    }

    // ---- Missing required fields (deterministic) ----

    public function test_missing_business_objective_is_detected(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject(['business_objective' => null]);
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $fields = collect($result['missing_required_information'])->pluck('field');
        $this->assertContains('business_objective', $fields);
        $this->assertEquals('needs_clarification', $result['analysis_status']);
    }

    public function test_missing_project_description_is_detected(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject(['description' => null]);
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $fields = collect($result['missing_required_information'])->pluck('field');
        $this->assertContains('project_description', $fields);
        $this->assertEquals('needs_clarification', $result['analysis_status']);
    }

    public function test_missing_start_date_is_detected(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject(['start_date' => null]);
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $fields = collect($result['missing_required_information'])->pluck('field');
        $this->assertContains('start_date', $fields);
        $this->assertEquals('needs_clarification', $result['analysis_status']);
    }

    // ---- Requirement type extraction ----

    public function test_functional_requirement_extraction(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertDatabaseHas('project_requirements', [
            'project_id' => $project->id,
            'requirement_type' => 'functional',
            'name' => 'Employee Management',
        ]);
    }

    public function test_non_functional_requirement_extraction(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertDatabaseHas('project_requirements', [
            'project_id' => $project->id,
            'requirement_type' => 'non_functional',
            'name' => 'Performance',
        ]);
    }

    public function test_integration_extraction(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $row = ProjectRequirement::where('project_id', $project->id)->where('requirement_type', 'integration')->first();
        $this->assertNotNull($row);
        $this->assertEquals('Email Service', $row->name);
        $this->assertEquals('Send notifications', $row->description);
    }

    public function test_user_role_extraction(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $roles = ProjectRequirement::where('project_id', $project->id)->where('requirement_type', 'user_role')->pluck('name');
        $this->assertEquals(['Employee', 'HR Staff', 'Manager', 'Administrator'], $roles->all());
    }

    // ---- Priority / mandatory preservation ----

    public function test_priority_is_preserved_and_not_invented(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertDatabaseHas('project_requirements', ['name' => 'Two-Factor Authentication', 'priority' => 'critical']);
        $this->assertDatabaseHas('project_requirements', ['name' => 'Employee Management', 'priority' => null]);
    }

    public function test_mandatory_flag_is_preserved_and_defaults_false(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertDatabaseHas('project_requirements', ['name' => 'Two-Factor Authentication', 'is_mandatory' => true]);
        $this->assertDatabaseHas('project_requirements', ['name' => 'Employee Management', 'is_mandatory' => false]);
    }

    // ---- Ambiguity detection ----

    public function test_ambiguity_detection_marks_needs_clarification(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([json_encode([
            'major_features' => [],
            'functional_requirements' => [],
            'non_functional_requirements' => [],
            'business_constraints' => [],
            'integrations' => [],
            'data_requirements' => [],
            'user_roles' => [],
            'platforms' => ['Web'],
            'clarifications_required' => [
                ['source_text' => 'It needs good security.', 'reason' => 'No specific security controls were stated.', 'clarification_question' => 'Which security requirements or standards must the system satisfy?'],
            ],
            'assumptions' => [],
        ])]);

        $project = $this->makeProject();
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertEquals('needs_clarification', $result['analysis_status']);
        $this->assertCount(1, $result['clarifications_required']);
        $this->assertDatabaseHas('project_requirements', [
            'project_id' => $project->id,
            'status' => 'needs_clarification',
            'clarification_question' => 'Which security requirements or standards must the system satisfy?',
        ]);
    }

    // ---- Failure handling ----

    public function test_invalid_llm_json_fails_the_analysis_without_persisting_requirements(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses(['this is not json', 'still not json']);

        $project = $this->makeProject();
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['analysis_status']);
        $this->assertEquals(0, ProjectRequirement::where('project_id', $project->id)->count());
        $this->assertDatabaseHas('requirement_analysis_runs', ['project_id' => $project->id, 'status' => 'failed']);
    }

    public function test_llm_failure_is_handled_gracefully(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([new LLMException('LLM provider (Ollama) unavailable.')]);

        $project = $this->makeProject();
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['analysis_status']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_invalid_json_is_retried_once_before_succeeding(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses(['not json at all', $this->completeCannedJson()]);

        $project = $this->makeProject();
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $this->assertTrue($result['success']);
        $this->assertEquals('complete', $result['analysis_status']);
    }

    // ---- Company scoping ----

    public function test_cross_company_project_cannot_be_analyzed(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $companyA = Company::create(['name' => 'Company A']);
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $projectB = $this->makeProject(); // belongs to a different company

        $response = $this->actingAs($userA)->postJson("/projects/{$projectB->id}/analyze-requirements");

        $response->assertStatus(404);
    }

    // ---- Persistence & re-analysis ----

    public function test_analysis_run_is_persisted_with_structured_output(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        $result = app(\App\Services\Requirements\RequirementAnalysisService::class)->analyze($project);

        $run = RequirementAnalysisRun::find($result['run_id']);
        $this->assertNotNull($run);
        $this->assertEquals('complete', $run->status);
        $this->assertNotNull($run->structured_output);
        $this->assertEquals('complete', $project->fresh()->requirement_analysis_status);
    }

    public function test_reanalysis_replaces_requirements_without_duplicating(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $project = $this->makeProject();
        $service = app(\App\Services\Requirements\RequirementAnalysisService::class);

        $service->analyze($project);
        $firstCount = ProjectRequirement::where('project_id', $project->id)->count();

        $this->bindLlmResponses([$this->completeCannedJson()]);
        $service = app(\App\Services\Requirements\RequirementAnalysisService::class);
        $service->analyze($project->fresh());
        $secondCount = ProjectRequirement::where('project_id', $project->id)->count();

        $this->assertEquals($firstCount, $secondCount);
        $this->assertEquals(2, RequirementAnalysisRun::where('project_id', $project->id)->count());
    }

    public function test_endpoint_returns_structured_response(): void
    {
        $this->fakeEmptyRuleRetrieval();
        $this->bindLlmResponses([$this->completeCannedJson()]);

        $company = Company::create(['name' => 'Endpoint Co']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $project = Project::create([
            'company_id' => $company->id,
            'name' => 'Test Project',
            'business_objective' => 'Objective',
            'description' => 'Description',
            'requirements_raw' => '- Login',
            'start_date' => '2026-09-01',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/analyze-requirements");

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'project_id', 'analysis_status', 'summary', 'requirements', 'missing_required_information', 'clarifications_required']);
    }
}
