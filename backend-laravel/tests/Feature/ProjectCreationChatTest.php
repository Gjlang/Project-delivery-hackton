<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyRule;
use App\Models\Project;
use App\Models\ProjectCreationSession;
use App\Models\ProjectRuleMatch;
use App\Models\RuleCategory;
use App\Models\RuleChunk;
use App\Models\User;
use App\Services\LLM\LLMException;
use App\Services\LLM\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectCreationChatTest extends TestCase
{
    use RefreshDatabase;

    private function makeReadyCompanyWithRule(array $overrides = []): array
    {
        $company = Company::create(['name' => 'Test Co']);
        $category = RuleCategory::firstOrCreate(['code' => $overrides['category_code'] ?? 'BR'], ['name' => 'Business Rules']);

        $rule = CompanyRule::create([
            'company_id' => $company->id,
            'rule_category_id' => $category->id,
            'rule_code' => $overrides['rule_code'] ?? 'BR-024',
            'title' => $overrides['title'] ?? 'Web Application Project',
            'rule_text' => $overrides['rule_text'] ?? 'Applies to any browser-based application built for internal or external users.',
            'applicable_condition' => 'The project is delivered as a web application.',
            'version' => '1.0',
            'status' => 'active',
            'is_active' => true,
        ]);

        RuleChunk::create([
            'company_rule_id' => $rule->id,
            'chunk_index' => 0,
            'chunk_text' => 'chunk text',
            'embedding_status' => 'embedded',
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);

        return [$company, $user, $rule];
    }

    private function fakeOllamaEmbeddings(): void
    {
        Http::fake([
            '*/api/embed' => function ($request) {
                $count = count($request->data()['input'] ?? []);

                return Http::response(['model' => 'nomic-embed-text', 'embeddings' => array_fill(0, $count, array_fill(0, 4, 0.1))]);
            },
        ]);
    }

    private function fakeQdrantSearch(CompanyRule $rule, string $category): void
    {
        Http::fake([
            '*/api/embed' => function ($request) {
                $count = count($request->data()['input'] ?? []);

                return Http::response(['model' => 'nomic-embed-text', 'embeddings' => array_fill(0, $count, array_fill(0, 4, 0.1))]);
            },
            '*/collections/*/points/search' => Http::response(['result' => [
                ['id' => RuleChunk::where('company_rule_id', $rule->id)->first()->id, 'score' => 0.9, 'payload' => ['company_rule_id' => $rule->id, 'category' => $category]],
            ]]),
        ]);
    }

    /**
     * @param  array<int, string|\Throwable>  $responses  Returned in order, last one repeats.
     */
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

    private function genericDecisionResponse(array $patch = [], array $clarifications = [], string $status = 'gathering'): string
    {
        return json_encode([
            'assistant_message' => 'Understood, thanks.',
            'draft_patch' => $patch,
            'clarifications' => $clarifications,
            'analysis_status' => $status,
        ]);
    }

    private function projectTypeResponse(string $ruleCode, string $confidence = 'high'): string
    {
        return json_encode([
            'assistant_message' => 'This looks like a web application project.',
            'primary_project_type' => 'Web Application Project',
            'secondary_project_types' => [],
            'source_rules' => [$ruleCode],
            'confidence' => $confidence,
            'clarifications' => [],
            'analysis_status' => 'gathering',
        ]);
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
        [$company, $user] = $this->makeReadyCompanyWithRule();
        $this->fakeOllamaEmbeddings();

        $response = $this->actingAs($user)->postJson('/projects/new/session');

        $response->assertStatus(201);
        $session = ProjectCreationSession::first();
        $this->assertSame($company->id, $session->company_id);
        $this->assertSame($user->id, $session->user_id);
    }

    public function test_it_rejects_access_to_another_companys_session_with_404(): void
    {
        [$companyA, $userA] = $this->makeReadyCompanyWithRule();
        [$companyB, $userB] = $this->makeReadyCompanyWithRule(['rule_code' => 'BR-099']);

        $session = ProjectCreationSession::create([
            'company_id' => $companyA->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);

        $this->actingAs($userB)->getJson("/projects/new/sessions/{$session->id}")->assertStatus(404);
        $this->actingAs($userB)->postJson("/projects/new/sessions/{$session->id}/messages", ['message' => 'hi'])->assertStatus(404);
    }

    public function test_it_posts_a_message_and_returns_a_validated_structured_response(): void
    {
        [$company, $user, $rule] = $this->makeReadyCompanyWithRule();
        $this->fakeQdrantSearch($rule, 'BR');
        $this->bindLlmResponses([
            $this->genericDecisionResponse(['business_objective' => 'Reduce manual HR work.']),
        ]);

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/messages", [
            'message' => 'We need a web-based employee management system.',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['assistant_message', 'draft', 'clarifications', 'analysis_status', 'session_status']);
        $this->assertSame('Reduce manual HR work.', $response->json('draft.business_objective'));
    }

    public function test_it_does_not_overwrite_a_confirmed_draft_field_from_unrelated_llm_output(): void
    {
        [$company, $user, $rule] = $this->makeReadyCompanyWithRule();
        $this->fakeQdrantSearch($rule, 'BR');
        $this->bindLlmResponses([
            $this->genericDecisionResponse(['business_objective' => 'Original objective.']),
            $this->genericDecisionResponse(['business_objective' => 'A different objective entirely.']),
        ]);

        $session = ProjectCreationSession::create(['company_id' => $company->id, 'user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/messages", ['message' => 'First message.'])->assertOk();
        $second = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/messages", ['message' => 'Second message.']);

        $second->assertOk();
        $this->assertSame('Original objective.', $second->json('draft.business_objective'));
    }

    public function test_it_confirms_and_creates_exactly_one_project(): void
    {
        [$company, $user, $rule] = $this->makeReadyCompanyWithRule();

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'ready_to_confirm',
            'analysis_status' => 'ready',
            'draft_data' => [
                'name' => 'HR Portal',
                'description' => 'Employee management system.',
                'business_objective' => 'Reduce manual HR work.',
                'start_date' => '2026-09-01',
                'primary_project_type' => 'Web Application Project',
            ],
            'decision_progress' => [
                'required_info' => ['status' => 'resolved', 'matches' => [
                    ['company_rule_id' => $rule->id, 'decision' => 'applied', 'similarity_score' => 0.9, 'reason' => 'context', 'source_reference' => $rule->rule_code],
                ]],
                'project_type' => ['status' => 'resolved', 'matches' => [
                    ['company_rule_id' => $rule->id, 'decision' => 'applied', 'similarity_score' => 0.9, 'reason' => 'match', 'source_reference' => $rule->rule_code],
                ]],
            ],
            'clarifications' => [],
        ]);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm");

        $response->assertOk();
        $this->assertSame(1, Project::count());
        $project = Project::first();
        $this->assertSame('HR Portal', $project->name);
        $this->assertSame('ai_chat', $project->creation_source);
        $this->assertSame($session->fresh()->confirmed_project_id, $project->id);
        $this->assertSame(2, ProjectRuleMatch::where('project_id', $project->id)->count());
    }

    public function test_it_is_idempotent_on_double_confirm_and_returns_the_same_project(): void
    {
        [$company, $user, $rule] = $this->makeReadyCompanyWithRule();

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'ready_to_confirm',
            'analysis_status' => 'ready',
            'draft_data' => ['name' => 'HR Portal', 'description' => 'd', 'business_objective' => 'o', 'start_date' => '2026-09-01'],
            'decision_progress' => [
                'required_info' => ['status' => 'resolved', 'matches' => []],
                'project_type' => ['status' => 'resolved', 'matches' => []],
            ],
            'clarifications' => [],
        ]);

        $first = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm");
        $second = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm");

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(1, Project::count());
        $this->assertSame($first->json('project.id'), $second->json('project.id'));
    }

    public function test_it_blocks_confirm_with_not_ready_when_required_decisions_are_unresolved(): void
    {
        [$company, $user] = $this->makeReadyCompanyWithRule();

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'active',
            'draft_data' => ['name' => 'HR Portal'],
            'decision_progress' => [],
            'clarifications' => [],
        ]);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm");

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'NOT_READY']);
        $this->assertSame(0, Project::count());
    }

    public function test_it_cancels_a_session_without_creating_a_project(): void
    {
        [$company, $user] = $this->makeReadyCompanyWithRule();

        $session = ProjectCreationSession::create(['company_id' => $company->id, 'user_id' => $user->id, 'status' => 'active']);

        $response = $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/cancel");

        $response->assertOk();
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertSame(0, Project::count());
    }

    public function test_it_persists_project_rule_matches_scoped_to_company_and_project(): void
    {
        [$company, $user, $rule] = $this->makeReadyCompanyWithRule();

        $session = ProjectCreationSession::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'ready_to_confirm',
            'analysis_status' => 'ready',
            'draft_data' => ['name' => 'HR Portal', 'description' => 'd', 'business_objective' => 'o', 'start_date' => '2026-09-01'],
            'decision_progress' => [
                'required_info' => ['status' => 'resolved', 'matches' => [
                    ['company_rule_id' => $rule->id, 'decision' => 'applied', 'similarity_score' => 0.8, 'reason' => 'r', 'source_reference' => $rule->rule_code],
                ]],
                'project_type' => ['status' => 'resolved', 'matches' => []],
            ],
            'clarifications' => [],
        ]);

        $this->actingAs($user)->postJson("/projects/new/sessions/{$session->id}/confirm")->assertOk();

        $match = ProjectRuleMatch::first();
        $this->assertSame($company->id, $match->company_id);
        $this->assertSame($rule->id, $match->company_rule_id);
        $this->assertSame('applied', $match->decision);
    }
}
