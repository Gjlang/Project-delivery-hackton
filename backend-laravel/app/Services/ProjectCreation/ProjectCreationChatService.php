<?php

namespace App\Services\ProjectCreation;

use App\Models\Project;
use App\Models\ProjectCreationMessage;
use App\Models\ProjectCreationSession;
use App\Models\ProjectRuleMatch;
use App\Models\User;
use App\Services\Assistant\AssistantMemoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP proxy to the Python LangGraph project-creation orchestrator
 * (python-service/threads.py). Laravel keeps ownership/authorization,
 * session bookkeeping, and Project creation; all decision-making (scope
 * checks, Qdrant rule retrieval, batched rule validation, role/employee
 * matching, plan generation) lives in Python -- same split as
 * KnowledgeBaseController::upload() -> FastAPI.
 */
class ProjectCreationChatService
{
    public function __construct(
        private readonly AssistantMemoryService $memory,
    ) {
    }

    public function startSession(int $companyId, int $userId, bool $forceNew = false): ProjectCreationSession
    {
        $thread = $this->memory->ensureThreadForUser(User::findOrFail($userId));

        $existing = $forceNew ? null : ProjectCreationSession::forCompany($companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($existing) {
            if (! $existing->assistant_thread_id) {
                $existing->update(['assistant_thread_id' => $thread->id]);
            }

            return $existing;
        }

        $session = ProjectCreationSession::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'assistant_thread_id' => $thread->id,
            'status' => 'active',
            'analysis_status' => 'gathering',
            'draft_data' => [],
            'decision_progress' => [],
            'clarifications' => [],
        ]);

        $welcome = "Describe the project your company wants to build. I'll ask follow-up questions and check it against your company's rules as we go.";

        $this->http()->post('/threads', [
            'thread_id' => (string) $session->id,
            'company_id' => $companyId,
        ]);

        ProjectCreationMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $welcome,
        ]);

        return $session;
    }

    /**
     * @return array{assistant_message: string, draft: array, clarifications: array, analysis_status: string, session_status: string}
     */
    public function postUserMessage(ProjectCreationSession $session, string $userText): array
    {
        ProjectCreationMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $userText,
        ]);

        try {
            $response = $this->http()->post("/threads/{$session->id}/messages", [
                'company_id' => $session->company_id,
                'message' => $userText,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Indexer service returned '.$response->status());
            }

            $data = $response->json();
        } catch (\Throwable $e) {
            Log::error('Project creation chat: orchestrator call failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            $assistantMessage = 'Sorry, I had trouble processing that. Could you rephrase or try again?';

            ProjectCreationMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $assistantMessage,
            ]);

            return [
                'assistant_message' => $assistantMessage,
                'draft' => $this->normalizeDraft($session->draft_data ?? [], null, null, $session->analysis_status),
                'clarifications' => $session->clarifications ?? [],
                'analysis_status' => $session->analysis_status,
                'session_status' => $session->status,
            ];
        }

        $rawProject = $data['draft'] ?? [];
        $primaryRole = $data['primary_role'] ?? null;
        $recommendedEmployee = $data['recommended_employee'] ?? null;
        $analysisStatus = $data['analysis_status'] ?? 'gathering';

        $clarifications = collect($data['clarifications'] ?? [])->map(fn ($c) => [
            'field' => null,
            'question' => $c['question'],
            'reason' => implode(', ', $c['requested_information'] ?? []),
            'decision_key' => null,
        ])->all();

        $session->update([
            'draft_data' => $rawProject,
            'decision_progress' => [
                'primary_role' => $primaryRole,
                'recommended_employee' => $recommendedEmployee,
            ],
            'clarifications' => $clarifications,
            'analysis_status' => $analysisStatus,
            'status' => $analysisStatus === 'ready' ? 'ready_to_confirm' : $session->status,
        ]);

        $assistantMessage = $data['assistant_message'] ?: "Got it -- what else can you tell me?";

        ProjectCreationMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $assistantMessage,
            'metadata' => ['clarifications' => $clarifications, 'analysis_status' => $analysisStatus],
        ]);

        return [
            'assistant_message' => $assistantMessage,
            'draft' => $this->normalizeDraft($rawProject, $primaryRole, $recommendedEmployee, $analysisStatus),
            'clarifications' => $clarifications,
            'analysis_status' => $analysisStatus,
            'session_status' => $session->status,
        ];
    }

    /**
     * ChatGPT-style conversation history list, newest first.
     *
     * @return array<int, array{session_id: int, title: string, status: string, analysis_status: string, updated_at: ?string}>
     */
    public function listSessions(int $companyId, int $userId): array
    {
        return ProjectCreationSession::forCompany($companyId)
            ->where('user_id', $userId)
            ->latest('updated_at')
            ->get()
            ->map(function (ProjectCreationSession $session) {
                $draft = $session->draft_data ?? [];

                $title = $draft['project_name']
                    ?? $draft['description']
                    ?? optional($session->messages()->where('role', 'user')->first())->content
                    ?? 'New conversation';

                return [
                    'session_id' => $session->id,
                    'title' => \Illuminate\Support\Str::limit((string) $title, 60),
                    'status' => $session->status,
                    'analysis_status' => $session->analysis_status,
                    'updated_at' => $session->updated_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return array{session_id: int, status: string, analysis_status: string, draft: array, clarifications: array, messages: array}
     */
    public function getState(ProjectCreationSession $session): array
    {
        $decisionProgress = $session->decision_progress ?? [];

        return [
            'session_id' => $session->id,
            'status' => $session->status,
            'analysis_status' => $session->analysis_status,
            'draft' => $this->normalizeDraft(
                $session->draft_data ?? [],
                $decisionProgress['primary_role'] ?? null,
                $decisionProgress['recommended_employee'] ?? null,
                $session->analysis_status,
            ),
            'clarifications' => $session->clarifications ?? [],
            'messages' => $session->messages->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Idempotent + transactional: a double-confirm never creates a second
     * project -- the row lock guarantees only the first caller creates it.
     */
    public function confirm(ProjectCreationSession $session): Project
    {
        return DB::transaction(function () use ($session) {
            $locked = ProjectCreationSession::whereKey($session->id)->lockForUpdate()->first();

            if ($locked->confirmed_project_id) {
                return $locked->confirmedProject;
            }

            if (! in_array($locked->status, ['active', 'ready_to_confirm'], true)) {
                throw new ProjectCreationException('SESSION_NOT_CONFIRMABLE', 'This session cannot be confirmed in its current state.');
            }

            $response = $this->http()->post("/threads/{$locked->id}/confirm");

            if ($response->status() === 422) {
                throw new ProjectCreationException('NOT_READY', 'Required information is still missing or unresolved.');
            }

            if (! $response->successful()) {
                throw new ProjectCreationException('CONFIRM_FAILED', 'Could not finalize the project plan. Please try again.');
            }

            $data = $response->json();
            $draft = $data['draft'] ?? ($locked->draft_data ?? []);
            $finalPlan = $data['final_plan'] ?? null;
            $recommendedEmployee = $data['recommended_employee'] ?? null;

            $project = Project::create([
                'company_id' => $locked->company_id,
                'created_by' => $locked->user_id,
                'name' => $finalPlan['project_name'] ?? $draft['project_name'] ?? $draft['description'] ?? 'Untitled Project',
                'description' => $draft['description'] ?? null,
                'business_objective' => $draft['business_problem'] ?? $draft['expected_outcome'] ?? null,
                'requirements_raw' => implode("\n", $draft['features'] ?? []),
                'start_date' => $this->parseStartDate($draft['dates'] ?? []),
                'status' => 'draft',
                'primary_project_type' => $draft['project_type_hint'] ?? null,
                'project_characteristics' => $draft,
                'creation_source' => 'ai_chat',
                'ai_generated_plan' => $finalPlan,
                'recommended_employee_id' => $recommendedEmployee['id'] ?? null,
            ]);

            foreach (($data['rule_matches'] ?? []) as $match) {
                if (empty($match['rule_id'])) {
                    continue;
                }

                ProjectRuleMatch::create([
                    'company_id' => $locked->company_id,
                    'project_id' => $project->id,
                    'rule_type' => $match['category'] ?? 'business_rules',
                    'rule_id' => $match['rule_id'],
                    'rule_code' => $match['rule_code'] ?? null,
                    'context' => 'business_rules',
                    'decision' => 'applied',
                    'reason' => $match['reason'] ?? null,
                    'source_reference' => $match['rule_code'] ?? null,
                ]);
            }

            $locked->update([
                'status' => 'confirmed',
                'confirmed_project_id' => $project->id,
                'confirmed_at' => now(),
            ]);

            return $project;
        });
    }

    public function cancel(ProjectCreationSession $session): void
    {
        if ($session->confirmed_project_id) {
            return;
        }

        try {
            $this->http()->post("/threads/{$session->id}/cancel");
        } catch (\Throwable $e) {
            Log::warning('Project creation chat: cancel proxy call failed', ['error' => $e->getMessage()]);
        }

        $session->update(['status' => 'cancelled']);
    }

    // -- Draft shape for the existing Blade/Alpine frontend ------------------

    /**
     * Maps the Python graph's generic ProjectFacts shape (project_name,
     * business_problem, description, user_roles, dates, ...) onto the field
     * names the existing new-chat.blade.php draft pane already binds to
     * (name, primary_project_type, classification_status,
     * business_objective, start_date, has_authentication, roles) so the
     * frontend needs no changes.
     */
    private function normalizeDraft(array $project, ?string $primaryRole, ?array $recommendedEmployee, string $analysisStatus): array
    {
        $authKeywords = ['auth', 'login', 'log in', 'sign in', 'password'];
        $haystack = strtolower(implode(' ', array_merge(
            $project['features'] ?? [],
            $project['constraints'] ?? [],
            $project['other_facts'] ?? [],
        )));

        $hasAuthentication = false;
        foreach ($authKeywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                $hasAuthentication = true;
                break;
            }
        }

        return [
            'name' => $project['project_name'] ?? null,
            'description' => $project['description'] ?? null,
            'primary_project_type' => $project['project_type_hint'] ?? $primaryRole,
            'classification_status' => $analysisStatus === 'ready' ? 'confirmed' : null,
            'business_objective' => $project['business_problem'] ?? $project['expected_outcome'] ?? null,
            'start_date' => $this->parseStartDate($project['dates'] ?? [])?->toDateString(),
            'has_authentication' => $hasAuthentication,
            'roles' => $project['user_roles'] ?? [],
            'primary_role' => $primaryRole,
            'recommended_employee' => $recommendedEmployee,
        ];
    }

    private function parseStartDate(array $dates): ?Carbon
    {
        foreach ($dates as $candidate) {
            try {
                return Carbon::parse($candidate);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function http()
    {
        return Http::baseUrl(config('services.python_indexer.base_url'))
            ->withHeaders(['X-API-Key' => config('services.python_indexer.api_key')])
            ->timeout(60);
    }
}
