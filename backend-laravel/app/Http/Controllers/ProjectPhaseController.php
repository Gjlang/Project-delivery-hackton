<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\TestingResultRuleCheck;
use App\Services\Testing\TestingResultRuleValidationService;
use Illuminate\Http\Request;

class ProjectPhaseController extends Controller
{
    /**
     * GET /phases -- every one of the user's projects with its current
     * phase, so they can jump straight into any project's phase tracker
     * without going through the project itself first.
     */
    public function overview(Request $request)
    {
        $projects = Project::where('created_by', $request->user()->id)
            ->with('phases')
            ->latest()
            ->get()
            ->map(function (Project $project) {
                $current = $project->phases->firstWhere('status', 'in_progress')
                    ?? $project->phases->last();

                return [
                    'project' => $project,
                    'total_phases' => $project->phases->count(),
                    'done_phases' => $project->phases->where('status', 'done')->count(),
                    'current' => $current,
                ];
            });

        return view('projects.phases-overview', ['rows' => $projects]);
    }

    public function index(Request $request, Project $project)
    {
        ProjectController::authorize($request, $project);

        return view('projects.phases', [
            'project' => $project,
            'phases' => $project->phases,
            'latestRun' => $project->testRuns()->latest()->first(),
            'latestChecks' => $this->latestTestingChecks($project),
        ]);
    }

    /**
     * Strictly sequential: only the currently in_progress phase can be
     * marked done, which auto-activates the next phase_number if one
     * exists. There's no way to skip ahead or un-complete a phase. Phase 5
     * ("Testing and Validation") additionally requires its Testing
     * Standards check to have passed.
     */
    public function markDone(Request $request, Project $project, ProjectPhase $phase)
    {
        ProjectController::authorize($request, $project);
        abort_unless($phase->project_id === $project->id, 404);

        if ($phase->status !== 'in_progress') {
            return back()->withErrors(['phase' => 'This phase is not currently in progress.']);
        }

        if ($phase->phase_number === 5) {
            $hasTestingRules = \App\Models\TestingResultRule::where('created_by', $request->user()->id)->exists();

            if ($hasTestingRules) {
                $checks = $this->latestTestingChecks($project);

                if ($checks->isEmpty() || $checks->contains(fn ($c) => in_array($c->status, ['FAIL', 'NEEDS_INFORMATION'], true))) {
                    return back()->withErrors([
                        'testing' => 'Insert your website URL and run a Playwright test first, then check Testing Standards, before this phase can be marked done.',
                    ]);
                }
            }
        }

        $phase->update(['status' => 'done', 'completed_at' => now()]);

        $next = $project->phases()->where('phase_number', '>', $phase->phase_number)->first();
        if ($next) {
            $next->update(['status' => 'in_progress', 'started_at' => now()]);
        }

        return back()->with('status', "Phase {$phase->phase_number} ({$phase->phase_name}) marked done.");
    }

    /**
     * POST /projects/{project}/phases/testing-check -- runs the project
     * owner's Testing Standards ("TR") rules against the project's latest
     * Playwright test run and persists the result.
     */
    public function checkTestingRules(Request $request, Project $project, TestingResultRuleValidationService $validator)
    {
        ProjectController::authorize($request, $project);

        $run = $project->testRuns()->latest()->first();

        if (! $run) {
            return back()->withErrors(['testing' => 'Insert your website URL and run a Playwright test for this project before checking Testing Standards.']);
        }

        $validator->validate($run, $request->user()->id);

        return back()->with('status', 'Testing Standards check complete.');
    }

    private function latestTestingChecks(Project $project): \Illuminate\Support\Collection
    {
        $run = $project->testRuns()->latest()->first();

        if (! $run) {
            return collect();
        }

        return TestingResultRuleCheck::where('website_test_run_id', $run->id)->get();
    }
}
