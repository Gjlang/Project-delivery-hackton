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
        $state = $readiness->evaluate($request->user()->id);

        if ($readiness->isBlocking($request->user()->id)) {
            return response()->json([
                'error_code' => 'COMPANY_RULES_REQUIRED',
                'status' => $state['status'],
                'message' => 'Set up your company rules before creating a project.',
            ], 422);
        }

        $session = $chat->startSession($request->user()->id, forceNew: $request->boolean('new'));

        return response()->json(['session' => $chat->getState($session)], 201);
    }

    public function index(Request $request, ProjectCreationChatService $chat)
    {
        return response()->json([
            'sessions' => $chat->listSessions($request->user()->id),
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

        if ($readiness->isBlocking($session->user_id)) {
            return response()->json([
                'error_code' => 'COMPANY_RULES_REQUIRED',
                'message' => 'You no longer have usable rules configured. Configure Company Rules before creating a project.',
            ], 422);
        }

        try {
            $project = $chat->confirm($session);
        } catch (ProjectCreationException $e) {
            return response()->json(['error_code' => $e->errorCode(), 'message' => $e->getMessage()], 422);
        }

        $employee = $project->recommendedEmployee;
        if ($employee) {
            session()->flash('status', "Project plan created successfully. Recommended: {$employee->name} ({$employee->role}).");
        }

        return response()->json([
            'project' => ['id' => $project->id, 'name' => $project->name],
            'recommended_employee' => $employee ? ['id' => $employee->id, 'name' => $employee->name, 'role' => $employee->role] : null,
            // Freshly created project lands on its phase tracker so the
            // owner can see the AI-generated plan and start marking progress.
            'redirect' => route('projects.phases.index', $project),
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
        abort_unless($session->user_id === $request->user()->id, 404);
    }
}
