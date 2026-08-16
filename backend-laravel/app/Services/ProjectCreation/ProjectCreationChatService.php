<?php

namespace App\Services\ProjectCreation;

use App\Models\Project;
use App\Models\ProjectCreationMessage;
use App\Models\ProjectCreationSession;
use App\Models\ProjectRuleMatch;
use App\Services\LLM\LLMException;
use App\Services\LLM\LLMService;
use App\Services\Rules\RuleRetrievalService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the AI project-creation chat: one focused decision at a time
 * (see DecisionDefinitions), each resolved via a targeted RuleRetrievalService
 * call + a narrow LLM call, never the whole rulebook or whole chat history at
 * once. Mirrors RequirementAnalysisService's shape (deterministic checks ->
 * targeted retrieval -> validated LLM call -> persist), but produces a
 * conversational draft instead of a one-shot analysis.
 */
class ProjectCreationChatService
{
    private const RECENT_HISTORY_LIMIT = 6;

    public function __construct(
        private readonly LLMService $llm,
        private readonly RuleRetrievalService $ruleRetrieval,
        private readonly ProjectCreationPromptBuilder $promptBuilder,
        private readonly ProjectCreationResponseValidator $validator,
    ) {
    }

    public function startSession(int $companyId, int $userId): ProjectCreationSession
    {
        $existing = ProjectCreationSession::forCompany($companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $session = ProjectCreationSession::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'status' => 'active',
            'analysis_status' => 'gathering',
            'draft_data' => [],
            'decision_progress' => [],
            'clarifications' => [],
        ]);

        ProjectCreationMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => "Describe the project your company wants to build. I'll ask follow-up questions and check it against your company's rules as we go.",
            'metadata' => ['decision_key' => null],
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

        $draft = $session->draft_data ?? [];
        $decisionProgress = $session->decision_progress ?? [];
        $priorClarifications = $session->clarifications ?? [];
        $outstandingFields = array_filter(array_column($priorClarifications, 'field'));

        // The first user message is treated as the project name/description
        // seed if nothing has been captured yet -- deterministic, not LLM.
        if (empty(trim((string) ($draft['description'] ?? '')))) {
            $draft['description'] = $userText;
            $draft['requirements_raw'] = trim(($draft['requirements_raw'] ?? '')."\n".$userText);
        } else {
            $draft['requirements_raw'] = trim(($draft['requirements_raw'] ?? '')."\n".$userText);
        }

        $text = $this->conversationText($session, $userText, $draft);
        $decisionKey = $this->pickNextDecision($draft, $decisionProgress, $text);

        $assistantMessage = '';
        $clarifications = $priorClarifications;

        if ($decisionKey !== null) {
            $decision = DecisionDefinitions::get($decisionKey);
            $retrieval = $this->safeRetrieve($session->company_id, $decision);
            $ruleSummaries = $this->buildRuleSummaries($retrieval, $decisionKey);
            $chatSummary = $this->recentChatSummary($session);

            $prompt = $this->promptBuilder->build($decisionKey, $decision['label'], $chatSummary, $draft, $ruleSummaries);

            try {
                $normalized = $this->runLlm($prompt, $decisionKey, $retrieval);

                if ($decisionKey === 'project_type') {
                    $draft = $this->applyProjectTypeResult($draft, $normalized, $retrieval);

                    if (($draft['classification_status'] ?? null) === 'needs_clarification' && empty($normalized['clarifications'])) {
                        $normalized['clarifications'][] = [
                            'field' => 'primary_project_type',
                            'question' => "What kind of project is this (e.g. internal business system, customer-facing web app, integration project)?",
                            'reason' => "No confident match among the company's project-type rules.",
                        ];
                    }

                    $decisionResolved = ($draft['classification_status'] ?? null) === 'confirmed';
                } else {
                    $draft = $this->applyDraftPatch($draft, $normalized['draft_patch'], $outstandingFields);
                    $decisionResolved = empty($normalized['clarifications']);
                }

                $assistantMessage = $normalized['assistant_message'] ?: "Got it -- what else can you tell me?";
                $clarifications = $this->mergeClarifications($clarifications, $normalized['clarifications'], $decisionKey);

                $decisionProgress[$decisionKey] = [
                    'status' => $decisionResolved ? 'resolved' : 'needs_clarification',
                    'matches' => $this->matchesForTrace($retrieval, $decisionKey, $normalized),
                    'resolved_at' => now()->toIso8601String(),
                ];
            } catch (LLMException $e) {
                Log::error('Project creation chat: LLM decision failed', [
                    'session_id' => $session->id,
                    'decision_key' => $decisionKey,
                    'error' => $e->getMessage(),
                ]);

                $assistantMessage = 'Sorry, I had trouble processing that. Could you rephrase or add a bit more detail?';
                // Leave decision_progress untouched so this decision is retried next turn.
            }
        }

        $missing = $this->deterministicMissingFields($draft);

        if ($decisionKey === null) {
            $assistantMessage = empty($missing) && empty($clarifications)
                ? "Thanks -- I think I have what I need. Review the draft on the right and click Create Project when you're ready."
                : "I still need a bit more information before we can finish.";
        }

        $status = $this->deriveAnalysisStatus($decisionProgress, $missing, $clarifications);

        $session->update([
            'draft_data' => $draft,
            'decision_progress' => $decisionProgress,
            'clarifications' => $clarifications,
            'analysis_status' => $status,
            'status' => $status === 'ready' ? 'ready_to_confirm' : $session->status,
        ]);

        ProjectCreationMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $assistantMessage,
            'metadata' => [
                'decision_key' => $decisionKey,
                'clarifications' => $clarifications,
                'analysis_status' => $status,
            ],
        ]);

        return [
            'assistant_message' => $assistantMessage,
            'draft' => $draft,
            'clarifications' => $clarifications,
            'analysis_status' => $status,
            'session_status' => $session->status,
        ];
    }

    /**
     * @return array{session_id: int, status: string, analysis_status: string, draft: array, clarifications: array, messages: array}
     */
    public function getState(ProjectCreationSession $session): array
    {
        return [
            'session_id' => $session->id,
            'status' => $session->status,
            'analysis_status' => $session->analysis_status,
            'draft' => $session->draft_data ?? [],
            'clarifications' => $session->clarifications ?? [],
            'messages' => $session->messages->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Idempotent + transactional: a double-confirm (double-click, race) never
     * creates a second project -- the row lock guarantees only the first
     * caller inside the transaction creates it, and every later caller
     * (including this same request retried) sees confirmed_project_id
     * already set and simply returns that project.
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

            if (! $this->requiredDecisionsResolved($locked)) {
                throw new ProjectCreationException('NOT_READY', 'Required information is still missing or unresolved.');
            }

            $draft = $locked->draft_data ?? [];

            $project = Project::create([
                'company_id' => $locked->company_id,
                'created_by' => $locked->user_id,
                'name' => $draft['name'] ?? Arr::get($draft, 'description', 'Untitled Project'),
                'description' => $draft['description'] ?? null,
                'business_objective' => $draft['business_objective'] ?? null,
                'requirements_raw' => $draft['requirements_raw'] ?? null,
                'start_date' => $draft['start_date'] ?? null,
                'status' => 'draft',
                'primary_project_type' => $draft['primary_project_type'] ?? null,
                'project_characteristics' => Arr::only($draft, [
                    'has_authentication', 'protected_routes', 'roles', 'platforms', 'integrations', 'secondary_project_types',
                ]),
                'creation_source' => 'ai_chat',
            ]);

            foreach ((array) $locked->decision_progress as $decisionKey => $progress) {
                foreach ((array) ($progress['matches'] ?? []) as $match) {
                    ProjectRuleMatch::create([
                        'company_id' => $locked->company_id,
                        'project_id' => $project->id,
                        'company_rule_id' => $match['company_rule_id'],
                        'context' => $decisionKey,
                        'decision' => $match['decision'],
                        'similarity_score' => $match['similarity_score'] ?? null,
                        'reason' => $match['reason'] ?? null,
                        'source_reference' => $match['source_reference'] ?? null,
                    ]);
                }
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

        $session->update(['status' => 'cancelled']);
    }

    // -- Decision selection ------------------------------------------------

    private function pickNextDecision(array $draft, array $decisionProgress, string $text): ?string
    {
        foreach (DecisionDefinitions::all() as $key => $decision) {
            $status = $decisionProgress[$key]['status'] ?? null;

            if (in_array($status, ['resolved', 'skipped'], true)) {
                continue;
            }

            if ($status === 'needs_clarification') {
                return $key;
            }

            if ($key === 'project_type' && ($decisionProgress['required_info']['status'] ?? null) !== 'resolved') {
                continue;
            }

            if (! $decision['required'] && ! $this->decisionTriggered($key, $text)) {
                continue;
            }

            return $key;
        }

        return null;
    }

    private function decisionTriggered(string $key, string $text): bool
    {
        $text = strtolower($text);

        $keywords = match ($key) {
            'authentication' => ['login', 'log in', 'sign in', 'authenticat', 'password', 'account'],
            'integrations' => ['integrat', 'api', 'third-party', 'third party', 'external system', 'connect to'],
            'compliance_constraints' => ['complian', 'regulat', 'governance', 'approval', 'audit'],
            default => [],
        };

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function conversationText(ProjectCreationSession $session, string $latestUserText, array $draft): string
    {
        $parts = [$latestUserText, (string) ($draft['description'] ?? ''), (string) ($draft['requirements_raw'] ?? '')];

        foreach ($session->messages as $message) {
            if ($message->role === 'user') {
                $parts[] = $message->content;
            }
        }

        return implode(' ', $parts);
    }

    private function recentChatSummary(ProjectCreationSession $session): array
    {
        return $session->messages()
            ->latest('created_at')
            ->take(self::RECENT_HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    // -- Rule retrieval ------------------------------------------------------

    private function safeRetrieve(int $companyId, array $decision): array
    {
        try {
            return $this->ruleRetrieval->retrieve($companyId, $decision['query'], $decision['categories'], $decision['top_k']);
        } catch (\Throwable $e) {
            Log::warning('Project creation chat: rule retrieval failed, continuing without context', ['error' => $e->getMessage()]);

            return ['query' => $decision['query'], 'results' => []];
        }
    }

    private function buildRuleSummaries(array $retrieval, string $decisionKey): array
    {
        return collect($retrieval['results'] ?? [])->map(fn ($r) => [
            'rule_code' => $r['rule_code'],
            'title' => $r['title'],
            'category' => $r['category'],
            'rule_text' => $decisionKey === 'project_type' ? ($r['rule']['rule_text'] ?? null) : null,
            'applicable_condition' => $decisionKey === 'project_type' ? ($r['rule']['applicable_condition'] ?? null) : null,
        ])->all();
    }

    // -- LLM call w/ retry-once, mirrors RequirementAnalysisService --------

    private function runLlm(string $prompt, string $decisionKey, array $retrieval): array
    {
        $allowedRuleCodes = $decisionKey === 'project_type'
            ? array_column($retrieval['results'] ?? [], 'rule_code')
            : [];

        $lastError = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $raw = $this->llm->generate($prompt, jsonMode: true);
            } catch (LLMException $e) {
                $lastError = $e;

                continue;
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $lastError = new LLMException('LLM returned invalid JSON: '.json_last_error_msg());

                continue;
            }

            $result = $this->validator->validate($decoded, $decisionKey, $allowedRuleCodes);
            if (! $result['valid']) {
                $lastError = new LLMException('LLM JSON did not match the expected schema.');

                continue;
            }

            return $result['normalized'];
        }

        throw $lastError ?? new LLMException('LLM extraction failed for an unknown reason.');
    }

    // -- Draft mutation --------------------------------------------------------

    private function applyDraftPatch(array $draft, array $patch, array $outstandingClarificationFields): array
    {
        foreach ($patch as $key => $value) {
            $hasExisting = array_key_exists($key, $draft) && $draft[$key] !== null && $draft[$key] !== '' && $draft[$key] !== [];
            $isAnsweringOwnClarification = in_array($key, $outstandingClarificationFields, true);

            if ($hasExisting && ! $isAnsweringOwnClarification) {
                continue;
            }

            $draft[$key] = $value;
        }

        return $draft;
    }

    /**
     * Project type is NOT decided by cosine rank alone: the LLM compares the
     * draft against the retrieved candidate rules and classifies. This only
     * applies a deterministic sanity check on top -- low confidence, or the
     * LLM's own pick disagreeing with the single highest-similarity
     * candidate, downgrades the result to "needs_clarification" rather than
     * accepting the guess.
     */
    private function applyProjectTypeResult(array $draft, array $normalized, array $retrieval): array
    {
        $topCandidateCode = $retrieval['results'][0]['rule_code'] ?? null;

        $uncertain = $normalized['confidence'] === 'low'
            || $normalized['primary_project_type'] === null
            || ($topCandidateCode !== null && ! in_array($topCandidateCode, $normalized['source_rules'], true));

        $draft['primary_project_type'] = $normalized['primary_project_type'];
        $draft['secondary_project_types'] = $normalized['secondary_project_types'];
        $draft['project_type_source_rules'] = $normalized['source_rules'];
        $draft['classification_status'] = $uncertain ? 'needs_clarification' : 'confirmed';

        return $draft;
    }

    private function mergeClarifications(array $existing, array $newOnes, string $decisionKey): array
    {
        $kept = array_values(array_filter($existing, fn ($c) => ($c['decision_key'] ?? null) !== $decisionKey));

        foreach ($newOnes as $c) {
            $kept[] = [
                'field' => $c['field'] ?? null,
                'question' => $c['question'],
                'reason' => $c['reason'] ?? null,
                'decision_key' => $decisionKey,
            ];
        }

        return $kept;
    }

    private function matchesForTrace(array $retrieval, string $decisionKey, array $normalized): array
    {
        $results = $retrieval['results'] ?? [];

        if ($decisionKey === 'project_type') {
            $accepted = $normalized['source_rules'] ?? [];

            return collect($results)->map(fn ($r) => [
                'company_rule_id' => $r['company_rule_id'],
                'decision' => in_array($r['rule_code'], $accepted, true) ? 'applied' : 'not_applicable',
                'similarity_score' => $r['similarity_score'] ?? null,
                'reason' => in_array($r['rule_code'], $accepted, true)
                    ? ($normalized['assistant_message'] ?: 'Matched project type classification.')
                    : 'Not selected as the matching project type.',
                'source_reference' => $r['rule_code'],
            ])->all();
        }

        return collect($results)->map(fn ($r) => [
            'company_rule_id' => $r['company_rule_id'],
            'decision' => 'applied',
            'similarity_score' => $r['similarity_score'] ?? null,
            'reason' => "Considered for decision: {$decisionKey}.",
            'source_reference' => $r['rule_code'],
        ])->all();
    }

    // -- Deterministic checks (never delegated to the LLM) ------------------

    /**
     * @return array<int, array{field: string, message: string}>
     */
    private function deterministicMissingFields(array $draft): array
    {
        $missing = [];

        if (empty(trim((string) ($draft['name'] ?? '')))) {
            $missing[] = ['field' => 'name', 'message' => 'Project name is required.'];
        }

        if (empty(trim((string) ($draft['business_objective'] ?? '')))) {
            $missing[] = ['field' => 'business_objective', 'message' => 'Business objective is required before project planning may begin.'];
        }

        if (empty(trim((string) ($draft['description'] ?? '')))) {
            $missing[] = ['field' => 'description', 'message' => 'Project description is required.'];
        }

        if (empty($draft['start_date'] ?? null)) {
            $missing[] = ['field' => 'start_date', 'message' => 'Start date is required as the scheduling reference point.'];
        }

        return $missing;
    }

    private function deriveAnalysisStatus(array $decisionProgress, array $missing, array $clarifications): string
    {
        if (! empty($missing) || ! empty($clarifications)) {
            return 'needs_clarification';
        }

        foreach (DecisionDefinitions::requiredKeys() as $key) {
            if (($decisionProgress[$key]['status'] ?? null) !== 'resolved') {
                return 'gathering';
            }
        }

        return 'ready';
    }

    private function requiredDecisionsResolved(ProjectCreationSession $session): bool
    {
        $draft = $session->draft_data ?? [];
        $decisionProgress = $session->decision_progress ?? [];

        if (! empty($this->deterministicMissingFields($draft))) {
            return false;
        }

        if (! empty($session->clarifications ?? [])) {
            return false;
        }

        foreach (DecisionDefinitions::requiredKeys() as $key) {
            if (($decisionProgress[$key]['status'] ?? null) !== 'resolved') {
                return false;
            }
        }

        return true;
    }
}
