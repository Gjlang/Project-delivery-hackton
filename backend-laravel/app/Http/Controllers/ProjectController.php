<?php

namespace App\Http\Controllers;

use App\Models\Project;
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

    public function create()
    {
        return view('projects.create');
    }

    public function store(Request $request)
    {
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

    /**
     * Guard: a project belongs to exactly one company, block cross-company access.
     */
    public static function authorize(Request $request, Project $project): void
    {
        abort_unless($project->company_id === $request->user()->company_id, 404);
    }
}
