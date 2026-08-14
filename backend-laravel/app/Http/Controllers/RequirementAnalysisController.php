<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Requirements\RequirementAnalysisService;
use Illuminate\Http\Request;

class RequirementAnalysisController extends Controller
{
    /**
     * POST /projects/{project}/analyze-requirements
     */
    public function analyze(Request $request, Project $project, RequirementAnalysisService $service)
    {
        ProjectController::authorize($request, $project);

        $result = $service->analyze($project);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
