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
        $projects = Project::where('company_id', $request->user()->company_id)
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
    public function create()
    {
        $categories = config('knowledge_rules');

        foreach ($categories as $prefix => &$meta) {
            $meta['count'] = $meta['model']::count();
        }
        unset($meta);

        $documents = Document::latest()->take(20)->get()->map(fn (Document $d) => [
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
        if ($readiness->isBlocking($request->user()->company_id)) {
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
     * Guard: a project belongs to exactly one company, block cross-company access.
     */
    public static function authorize(Request $request, Project $project): void
    {
        abort_unless($project->company_id === $request->user()->company_id, 404);
    }
}
