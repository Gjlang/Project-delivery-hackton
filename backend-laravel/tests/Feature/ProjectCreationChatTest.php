<?php

namespace Tests\Feature;

use App\Models\BusinessRule;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectCreationSession;
use App\Models\ProjectRuleMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ProjectCreationChatService is now a thin HTTP proxy to the Python
 * LangGraph orchestrator (python-service/threads.py) -- these tests fake
 * that HTTP boundary rather than an in-process LLM binding.
 */
class ProjectCreationChatTest extends TestCase
{
    use RefreshDatabase;

    private function makeReadyCompany(): array
    {
        $company = Company::create(['name' => 'Test Co']);
        $rule = BusinessRule::create([
            'rule_code' => 'BR-024',
            'title' => 'Web Application Project',
            'rule_text' => 'Applies to any browser-based application built for internal or external users.',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        return [$company, $user, $rule];
    }

    public function test_it_blocks_session_start_with_company_rules_required_when_no_active_rules(): void
    {
        $company = Company::create(['name' => 'Empty Co']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->postJson('/projects/new/session');

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'COMPANY_RULES_REQUIRED']);
        $this->assertSame(0, ProjectCreationSession::count());
    }

    public function test_it_creates_a_session_scoped_to_the_authenticated_users_company(): void
    {
        Http::fake(['*/threads' => Http::response(['assistant_message' => 'hi', 'draft' => [], 'clarifications' => [], 'analysis_status' => 'gathering'])]);

        [$company, $user] = $this->makeReadyCompany();

        $response = $this->actingAs($user)->postJson('/projects/new/session');

        $response->assertStatus(201);
        $session = ProjectCreationSession::first();
        $this->assertSame($company->id, $session->company_id);
        $this->assertSame($user->id, $session->user_id);
    }

    public function test_it_rejects_access_to_another_companys_session_with_404(): void
    {
        [$companyA, $userA] = $this->makeReadyCompany();
        $companyB = Company::create(['name' => 'Company B']);
        $userB = User::factory()->create(['company_id' => $companyB->id]);

        $session = ProjectCreationSession::create([
            'company_id' => $companyA->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);

        $this->actingAs($userB)->getJson("/projects/new/sessions/{$session->id}")->assertStatus(404);
        $this->actingAs($userB)->postJson("/projects/new/sessions/{$session->id}/messages", ['message' => 'hi'])->assertStatus(404);
    }

    public function test_it_posts_a_message_and_returns_a_normalized_draft(): void
    {
        Http::fake(['*/threads/*/messages' => Http::response([
            'assistant_message' => 'Understood, thanks.',
            'draft' => ['description' => 'Employee management system.', 'business_problem' => 'Reduce manual HR work.'],
            'primary_role' => null,
            'recommended_employee' => null,
            'clarifications' => [],
            'analysis_status' => 'gathering',
            'final_plan' => null,
        ])]);

        [$company, $user] = $this->makeReadyCompany();
        $session = ProjectCreationSession::create(['company_id' => $company->id, 'user_id' => $user->id, 'status' => 'active']);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/messages", [
            'message' => 'We need a web-based employee management system.',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['assistant_message', 'draft', 'clarifications', 'analysis_status', 'session_status']);
        $this->assertSame('Reduce manual HR work.', $response->json('draft.business_objective'));
    }

    public function test_it_surfaces_consolidated_clarifications_from_the_orchestrator(): void
    {
        Http::fake(['*/threads/*/messages' => Http::response([
            'assistant_message' => 'When should this start, and which provider will you integrate?',
            'draft' => ['description' => 'A loan portal.'],
            'primary_role' => null,
            'recommended_employee' => null,
            'clarifications' => [[
                'question' => 'When should this start, and which provider will you integrate?',
                'requested_information' => ['start date', 'payment provider'],
            ]],
            'analysis_status' => 'needs_clarification',
            'final_plan' => null,
        ])]);

        [$company, $user] = $this->makeReadyCompany();
        $session = ProjectCreationSession::create(['company_id' => $company->id, 'user_id' => $user->id, 'status' => 'active']);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/messages", ['message' => 'A loan portal.']);

        $response->assertOk();
        $this->assertCount(1, $response->json('clarifications'));
        $this->assertSame('needs_clarification', $response->json('analysis_status'));
    }

    public function test_it_confirms_and_creates_exactly_one_project(): void
    {
        [$company, $user, $rule] = $this->makeReadyCompany();

        Http::fake(['*/threads/*/confirm' => Http::response([
            'final_plan' => ['project_name' => 'HR Portal', 'project_summary' => 's', 'primary_role' => 'Backend Engineer', 'estimated_duration_days' => 10, 'phases' => []],
            'draft' => ['project_name' => 'HR Portal', 'description' => 'Employee management system.', 'business_problem' => 'Reduce manual HR work.', 'dates' => ['2026-09-01']],
            'primary_role' => 'Backend Engineer',
            'recommended_employee' => null,
            'rule_matches' => [
                ['rule_code' => $rule->rule_code, 'rule_id' => $rule->id, 'category' => 'business_rules', 'reason' => 'match'],
            ],
        ])]);

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'ready_to_confirm',
            'analysis_status' => 'ready',
            'draft_data' => ['project_name' => 'HR Portal'],
            'clarifications' => [],
        ]);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm");

        $response->assertOk();
        $this->assertSame(1, Project::count());
        $project = Project::first();
        $this->assertSame('HR Portal', $project->name);
        $this->assertSame('ai_chat', $project->creation_source);
        $this->assertSame($session->fresh()->confirmed_project_id, $project->id);
        $this->assertSame(1, ProjectRuleMatch::where('project_id', $project->id)->count());
    }

    public function test_it_is_idempotent_on_double_confirm_and_returns_the_same_project(): void
    {
        [$company, $user] = $this->makeReadyCompany();

        Http::fake(['*/threads/*/confirm' => Http::response([
            'final_plan' => ['project_name' => 'HR Portal', 'project_summary' => 's', 'primary_role' => 'Backend Engineer', 'estimated_duration_days' => 5, 'phases' => []],
            'draft' => ['project_name' => 'HR Portal', 'description' => 'd'],
            'primary_role' => 'Backend Engineer',
            'recommended_employee' => null,
            'rule_matches' => [],
        ])]);

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'ready_to_confirm',
            'analysis_status' => 'ready',
            'draft_data' => ['project_name' => 'HR Portal'],
            'clarifications' => [],
        ]);

        $first = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm");
        $second = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm");

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(1, Project::count());
        $this->assertSame($first->json('project.id'), $second->json('project.id'));
        Http::assertSentCount(1);
    }

    public function test_it_blocks_confirm_with_not_ready_when_the_orchestrator_reports_unresolved_rules(): void
    {
        Http::fake(['*/threads/*/confirm' => Http::response(['error_code' => 'NOT_READY', 'message' => 'Required information is still missing or unresolved.'], 422)]);

        [$company, $user] = $this->makeReadyCompany();

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'active',
            'draft_data' => ['project_name' => 'HR Portal'],
            'clarifications' => [],
        ]);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm");

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'NOT_READY']);
        $this->assertSame(0, Project::count());
    }

    public function test_it_cancels_a_session_without_creating_a_project(): void
    {
        Http::fake(['*/threads/*/cancel' => Http::response(['status' => 'cancelled'])]);

        [$company, $user] = $this->makeReadyCompany();
        $session = ProjectCreationSession::create(['company_id' => $company->id, 'user_id' => $user->id, 'status' => 'active']);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/cancel");

        $response->assertOk();
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertSame(0, Project::count());
    }

    public function test_it_persists_project_rule_matches_scoped_to_company_and_project(): void
    {
        [$company, $user, $rule] = $this->makeReadyCompany();

        Http::fake(['*/threads/*/confirm' => Http::response([
            'final_plan' => ['project_name' => 'HR Portal', 'project_summary' => 's', 'primary_role' => 'Backend Engineer', 'estimated_duration_days' => 5, 'phases' => []],
            'draft' => ['project_name' => 'HR Portal', 'description' => 'd'],
            'primary_role' => 'Backend Engineer',
            'recommended_employee' => null,
            'rule_matches' => [
                ['rule_code' => $rule->rule_code, 'rule_id' => $rule->id, 'category' => 'business_rules', 'reason' => 'r'],
            ],
        ])]);

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'ready_to_confirm',
            'analysis_status' => 'ready',
            'draft_data' => ['project_name' => 'HR Portal'],
            'clarifications' => [],
        ]);

        $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm")->assertOk();

        $match = ProjectRuleMatch::first();
        $this->assertSame($company->id, $match->company_id);
        $this->assertSame('business_rules', $match->rule_type);
        $this->assertSame($rule->id, $match->rule_id);
        $this->assertSame('applied', $match->decision);
    }
}
