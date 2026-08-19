<?php

namespace App\Http\Controllers;

use App\Models\ProjectCreationSession;
use App\Services\CompanyRules\CompanyRuleReadinessService;
use App\Services\ProjectCreation\ProjectCreationChatService;
use App\Services\ProjectCreation\ProjectCreationException;
use Illuminate\Http\Request;

class ProjectCreationChatController extends Controller
{
    public function start(Request $request, CompanyRuleReadinessService $readiness, ProjectCreationChatService $chat)
    {
        $companyId = $request->user()->company_id;
        $state = $readiness->evaluate($companyId);

        if ($readiness->isBlocking($companyId)) {
            return response()->json([
                'error_code' => 'COMPANY_RULES_REQUIRED',
                'status' => $state['status'],
                'message' => 'Set up your company rules before creating a project.',
            ], 422);
        }

        $session = $chat->startSession($companyId, $request->user()->id, forceNew: $request->boolean('new'));

        return response()->json(['session' => $chat->getState($session)], 201);
    }

    public function index(Request $request, ProjectCreationChatService $chat)
    {
        return response()->json([
            'sessions' => $chat->listSessions($request->user()->company_id, $request->user()->id),
        ]);
    }

    public function show(Request $request, ProjectCreationSession $session, ProjectCreationChatService $chat)
    {
        $this->authorizeSession($request, $session);

        return response()->json($chat->getState($session));
    }

    public function message(Request $request, ProjectCreationSession $session, ProjectCreationChatService $chat)
    {
        $this->authorizeSession($request, $session);

        $validated = $request->validate(['message' => 'required|string|max:4000']);

        $result = $chat->postUserMessage($session, $validated['message']);

        return response()->json($result);
    }

    public function confirm(Request $request, ProjectCreationSession $session, ProjectCreationChatService $chat, CompanyRuleReadinessService $readiness)
    {
        $this->authorizeSession($request, $session);

        if ($readiness->isBlocking($session->company_id)) {
            return response()->json([
                'error_code' => 'COMPANY_RULES_REQUIRED',
                'message' => 'This company no longer has usable rules configured. Configure Company Rules before creating a project.',
            ], 422);
        }

        try {
            $project = $chat->confirm($session);
        } catch (ProjectCreationException $e) {
            return response()->json(['error_code' => $e->errorCode(), 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'project' => ['id' => $project->id, 'name' => $project->name],
            // Playwright Testing is still being built out manually, so a
            // freshly created project lands straight there for testing
            // instead of Company Knowledge -- revisit once that flow is done.
            'redirect' => route('projects.testing.create', $project),
        ]);
    }

    public function cancel(Request $request, ProjectCreationSession $session, ProjectCreationChatService $chat)
    {
        $this->authorizeSession($request, $session);

        $chat->cancel($session);

        return response()->json(['status' => 'cancelled']);
    }

    private function authorizeSession(Request $request, ProjectCreationSession $session): void
    {
        abort_unless($session->company_id === $request->user()->company_id, 404);
    }
}
