<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Project;
use App\Services\CompanyRules\CompanyRuleReadinessService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::where('created_by', $request->user()->id)
            ->withCount('documents')
            ->latest()
            ->get();

        return view('projects.index', ['projects' => $projects]);
    }

    /**
     * GET /projects/new -- the AI chat project-creation shell. Also carries
     * the company-rules upload panel (folded into the same page's "Company
     * Rules" tab) so a first-run user never has to leave this page. The old
     * single-step form still exists (createLegacy/store) as an unlinked
     * fallback until the chat flow is verified end-to-end.
     */
    // Only these categories are offered on the Create-Project workspace's
    // Company Rules tab -- SC/AG uploads were dropped from this page on
    // request. Their tables/config stay intact system-wide (Playwright
    // Testing still reads SC/TS from config('testing_rules') independently
    // of this list), just not offered for upload here. TR is included since
    // Phase 5's testing-standards check depends on it and there's no other
    // upload entry point on this page's workflow.
    private const VISIBLE_KNOWLEDGE_CATEGORIES = ['BR', 'EW', 'TR'];

    public function create(Request $request)
    {
        $userId = $request->user()->id;

        $categories = collect(config('knowledge_rules'))
            ->only(self::VISIBLE_KNOWLEDGE_CATEGORIES)
            ->all();

        foreach ($categories as $prefix => &$meta) {
            $meta['count'] = $meta['model']::where('created_by', $userId)->count();
        }
        unset($meta);

        $documents = Document::whereIn('category', self::VISIBLE_KNOWLEDGE_CATEGORIES)
            ->where('uploaded_by', $userId)
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'original_filename' => $d->original_filename,
                'category' => $d->category,
                'date' => $d->created_at->format('M d, Y'),
                'status' => $d->status,
                'extracted_sections' => $d->extracted_sections,
            ])->values()->all();

        return view('projects.new-chat', [
            'categories' => $categories,
            'documents' => $documents,
        ]);
    }

    public function createLegacy()
    {
        return view('projects.create');
    }

    public function store(Request $request, CompanyRuleReadinessService $readiness)
    {
        if ($readiness->isBlocking($request->user()->id)) {
            return back()->withErrors([
                'company_rules' => 'COMPANY_RULES_REQUIRED: Set up your company rules before creating a project.',
            ])->withInput();
        }

        // Deliberately lenient beyond `name`: a project can be created with
        // incomplete information, and the Requirement Analysis Agent is what
        // surfaces exactly which required fields (BR-001/002/003) are
        // missing -- making these hard-required here would make that
        // detection path unreachable from the actual UI flow.
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_objective' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:2000',
            'requirements_raw' => 'nullable|string|max:5000',
            'start_date' => 'nullable|date',
        ]);

        $project = Project::create([
            ...$validated,
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return redirect()->route('projects.company-knowledge.index', $project)
            ->with('status', "\"{$project->name}\" created. Now upload the documents it should be built from.");
    }

    public function plan(Request $request, Project $project)
    {
        self::authorize($request, $project);

        return view('stubs.placeholder', [
            'title' => 'Project Plan',
            'description' => 'View and adjust the generated project plan.',
            'project' => $project,
        ]);
    }

    public function risks(Request $request, Project $project)
    {
        self::authorize($request, $project);

        return view('stubs.placeholder', [
            'title' => 'Risks',
            'description' => 'Track identified risks and mitigation strategies.',
            'project' => $project,
        ]);
    }

    /**
     * Guard: a project is only accessible by the user who created it --
     * isolation boundary is per-user, not per-company (company_id column is
     * kept but is no longer the access-control boundary).
     */
    public static function authorize(Request $request, Project $project): void
    {
        abort_unless($project->created_by === $request->user()->id, 404);
    }
}
