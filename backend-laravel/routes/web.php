<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyRuleController;
use App\Http\Controllers\CompanyRuleUiController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectCreationChatController;
use App\Http\Controllers\RequirementAnalysisController;
use App\Http\Controllers\TestingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/company/create', [CompanyController::class, 'create'])->name('company.create');
    Route::post('/company', [CompanyController::class, 'store'])->name('company.store');

    Route::middleware('ensure.company')->group(function () {
        Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
            $companyId = $request->user()->company_id;

            return view('dashboard', [
                'projects' => \App\Models\Project::where('company_id', $companyId)
                    ->withCount('documents')
                    ->latest()
                    ->take(5)
                    ->get(),
                'projectCount' => \App\Models\Project::where('company_id', $companyId)->count(),
                'documentCount' => \App\Models\Document::where('company_id', $companyId)->count(),
                'indexedCount' => \App\Models\Document::where('company_id', $companyId)->where('status', 'indexed')->count(),
            ]);
        })->middleware('verified')->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // New Project flow: create a project first, then Company Knowledge is
        // reached as a step scoped to that project (no longer a standalone
        // sidebar destination), then on into the rest of the flow.
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/new', [ProjectController::class, 'create'])->name('projects.new');
        Route::get('/projects/new/legacy', [ProjectController::class, 'createLegacy'])->name('projects.new.legacy');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

        Route::prefix('/projects/new')->name('projects.new-chat.')->group(function () {
            Route::post('/session', [ProjectCreationChatController::class, 'start'])->name('start');
            Route::get('/sessions/{session}', [ProjectCreationChatController::class, 'show'])->name('show');
            Route::post('/sessions/{session}/messages', [ProjectCreationChatController::class, 'message'])->name('message');
            Route::post('/sessions/{session}/confirm', [ProjectCreationChatController::class, 'confirm'])->name('confirm');
            Route::post('/sessions/{session}/cancel', [ProjectCreationChatController::class, 'cancel'])->name('cancel');
        });

        Route::get('/projects/{project}/plan', [ProjectController::class, 'plan'])->name('projects.plan.index');
        Route::get('/projects/{project}/risks', [ProjectController::class, 'risks'])->name('projects.risks.index');

        Route::prefix('/projects/{project}/company-knowledge')->name('projects.company-knowledge.')->group(function () {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::get('/library', [DocumentController::class, 'library'])->name('library');
            Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
            Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
            Route::post('/documents/{document}/reindex', [DocumentController::class, 'reindex'])->name('documents.reindex');
            Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        });

        Route::get('/projects/{project}/ai-analysis', [DocumentController::class, 'summary'])->name('projects.ai-analysis.index');

        Route::post('/projects/{project}/analyze-requirements', [RequirementAnalysisController::class, 'analyze'])->name('projects.analyze-requirements');

        Route::get('/testing', [TestingController::class, 'overview'])->name('testing.index');

        Route::prefix('/projects/{project}/testing')->name('projects.testing.')->group(function () {
            Route::get('/', [TestingController::class, 'create'])->name('create');
            Route::post('/', [TestingController::class, 'store'])->name('store');
            Route::get('/{run}', [TestingController::class, 'show'])->name('show');
            Route::get('/{run}/evidence/{result}', [TestingController::class, 'evidence'])->name('evidence');
        });

        // Company rules foundation (BR/CP/EW/SC/TS/AG). Session-authenticated
        // JSON endpoints -- no separate REST API layer exists in this app yet.
        // The Blade UI route must be registered before the `{companyRule}`
        // wildcard routes below, or "manage" would be captured as a rule ID.
        Route::get('/company-rules/manage', [CompanyRuleUiController::class, 'index'])->name('company-rules.ui.index');

        Route::prefix('/company-rules')->name('company-rules.')->group(function () {
            Route::get('/', [CompanyRuleController::class, 'index'])->name('index');
            Route::post('/', [CompanyRuleController::class, 'store'])->name('store');
            Route::post('/retrieve', [CompanyRuleController::class, 'retrieve'])->name('retrieve');
            Route::get('/{companyRule}', [CompanyRuleController::class, 'show'])->name('show');
            Route::put('/{companyRule}', [CompanyRuleController::class, 'update'])->name('update');
            Route::patch('/{companyRule}/status', [CompanyRuleController::class, 'updateStatus'])->name('update-status');
            Route::delete('/{companyRule}', [CompanyRuleController::class, 'destroy'])->name('destroy');
        });

        Route::view('/employees', 'stubs.placeholder', ['title' => 'Employees', 'description' => 'Manage employee profiles, roles, and availability here.'])->name('employees.index');
    });
});

require __DIR__.'/auth.php';
