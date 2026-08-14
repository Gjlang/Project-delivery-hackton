<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WebsiteTestResult;
use App\Models\WebsiteTestRun;
use App\Services\Testing\WebsiteTestingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TestingController extends Controller
{
    /**
     * GET /testing -- company-wide overview so "Testing" can live in the
     * sidebar despite being project-scoped underneath. Lists every project
     * with its latest run status and a direct link in.
     */
    public function overview(Request $request)
    {
        $projects = Project::where('company_id', $request->user()->company_id)
            ->with(['latestTestRun'])
            ->withCount('testRuns')
            ->latest()
            ->get();

        return view('testing.overview', ['projects' => $projects]);
    }

    /**
     * GET /projects/{project}/testing -- configuration form + run history.
     *
     * Formal project-type classification doesn't exist yet (Classification
     * Agent is a later phase). Per the brief, the temporary bridge is: the
     * user supplying a website URL here IS the eligibility signal that this
     * project has a deployed web target to test.
     */
    public function create(Request $request, Project $project)
    {
        ProjectController::authorize($request, $project);

        $runs = $project->testRuns()->withCount('results')->latest()->get();

        return view('testing.create', ['project' => $project, 'runs' => $runs]);
    }

    public function store(Request $request, Project $project, WebsiteTestingService $service)
    {
        ProjectController::authorize($request, $project);

        $validated = $request->validate([
            'website_url' => ['required', 'url', 'max:2048'],
            'protected_routes' => ['nullable', 'string'],
            'admin_routes' => ['nullable', 'string'],
            'login_url' => ['nullable', 'string', 'max:500'],
            'valid_username' => ['nullable', 'string', 'max:255'],
            'valid_password' => ['nullable', 'string', 'max:255'],
            'invalid_username' => ['nullable', 'string', 'max:255'],
            'invalid_password' => ['nullable', 'string', 'max:255'],
            'role_accounts' => ['nullable', 'string'],
            'critical_pages' => ['nullable', 'string'],
            'required_navigation' => ['nullable', 'string'],
            'test_form_page' => ['nullable', 'string', 'max:500'],
            'test_form_selector' => ['nullable', 'string', 'max:255'],
            'test_form_required_field_selector' => ['nullable', 'string', 'max:255'],
            'test_form_submit_selector' => ['nullable', 'string', 'max:255'],
            'test_form_invalid_value' => ['nullable', 'string', 'max:255'],
            'allow_duplicate_submission_test' => ['nullable', 'boolean'],
            'browsers' => ['nullable', 'array'],
            'browsers.*' => ['string', 'in:chromium,firefox,webkit'],
            'timeout_ms' => ['nullable', 'integer', 'min:2000', 'max:60000'],
        ]);

        $testContext = $this->buildTestContext($validated);

        $run = $service->run(
            $project,
            $validated['website_url'],
            $testContext,
            [
                'browsers' => $validated['browsers'] ?? ['chromium'],
                'timeout_ms' => $validated['timeout_ms'] ?? 15000,
            ],
            $request->user()->id
        );

        return redirect()->route('projects.testing.show', [$project, $run])
            ->with('status', "Test run completed: {$run->passed} passed, {$run->failed} failed, {$run->warnings} warnings, {$run->not_testable} not testable.");
    }

    /**
     * GET /projects/{project}/testing/{run} -- results dashboard.
     */
    public function show(Request $request, Project $project, WebsiteTestRun $run)
    {
        ProjectController::authorize($request, $project);
        $this->authorizeRun($project, $run);

        $query = $run->results()->with('companyRule')->orderByRaw("FIELD(status, 'FAIL', 'WARNING', 'NOT_TESTABLE', 'PASS')")->orderBy('rule_code');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        $results = $query->get();

        $previousRun = $project->testRuns()
            ->where('id', '<', $run->id)
            ->latest()
            ->first();

        $comparison = $previousRun ? $this->compareRuns($previousRun, $run) : null;

        return view('testing.show', [
            'project' => $project,
            'run' => $run,
            'results' => $results,
            'filters' => $request->only(['status', 'category', 'severity']),
            'previousRun' => $previousRun,
            'comparison' => $comparison,
        ]);
    }

    /**
     * GET /projects/{project}/testing/{run}/evidence/{result} -- serves a
     * screenshot through an authenticated, company-scoped route rather than
     * exposing the storage path directly.
     */
    public function evidence(Request $request, Project $project, WebsiteTestRun $run, WebsiteTestResult $result)
    {
        ProjectController::authorize($request, $project);
        $this->authorizeRun($project, $run);
        abort_unless($result->website_test_run_id === $run->id, 404);

        $screenshot = $result->evidence['screenshot'] ?? null;
        abort_unless($screenshot, 404);

        $path = "testing/project_{$project->id}/run_{$run->id}/{$screenshot}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function authorizeRun(Project $project, WebsiteTestRun $run): void
    {
        abort_unless($run->project_id === $project->id, 404);
    }

    private function buildTestContext(array $v): array
    {
        $context = [];

        if ($routes = $this->lines($v['protected_routes'] ?? null)) $context['protected_routes'] = $routes;
        if ($routes = $this->lines($v['admin_routes'] ?? null)) $context['admin_routes'] = $routes;
        if (! empty($v['login_url'])) $context['login_url'] = $v['login_url'];

        if (! empty($v['valid_username']) && ! empty($v['valid_password'])) {
            $context['valid_credentials'] = ['username' => $v['valid_username'], 'password' => $v['valid_password']];
        }
        if (! empty($v['invalid_username']) && ! empty($v['invalid_password'])) {
            $context['invalid_credentials'] = ['username' => $v['invalid_username'], 'password' => $v['invalid_password']];
        }

        if (! empty($v['role_accounts'])) {
            $roles = [];
            foreach ($this->lines($v['role_accounts']) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) === 3) {
                    $roles[] = ['role' => $parts[0], 'username' => $parts[1], 'password' => $parts[2]];
                }
            }
            if ($roles) $context['role_accounts'] = $roles;
        }

        if ($pages = $this->lines($v['critical_pages'] ?? null)) $context['critical_pages'] = $pages;
        if ($nav = $this->lines($v['required_navigation'] ?? null)) $context['required_navigation'] = $nav;

        if (! empty($v['test_form_page']) && ! empty($v['test_form_submit_selector'])) {
            $context['test_forms'] = [array_filter([
                'page' => $v['test_form_page'],
                'form_selector' => $v['test_form_selector'] ?? null,
                'required_field_selector' => $v['test_form_required_field_selector'] ?? null,
                'submit_selector' => $v['test_form_submit_selector'],
                'invalid_value' => $v['test_form_invalid_value'] ?? null,
            ])];
        }

        if (! empty($v['allow_duplicate_submission_test'])) {
            $context['allow_duplicate_submission_test'] = true;
        }

        return $context;
    }

    private function lines(?string $value): array
    {
        if (! $value) return [];

        return array_values(array_filter(array_map(fn ($l) => ltrim(trim($l), "-• \t"), preg_split('/\r\n|\r|\n/', $value))));
    }

    private function compareRuns(WebsiteTestRun $previous, WebsiteTestRun $current): array
    {
        $previousByCode = $previous->results()->get()->keyBy('rule_code');
        $currentByCode = $current->results()->get()->keyBy('rule_code');

        $changes = [];
        foreach ($currentByCode as $code => $result) {
            $prev = $previousByCode->get($code);
            if ($prev && $prev->status !== $result->status) {
                $changes[] = ['rule_code' => $code, 'previous' => $prev->status, 'latest' => $result->status];
            }
        }

        return $changes;
    }
}
