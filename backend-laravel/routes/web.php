<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
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
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

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

        Route::view('/employees', 'stubs.placeholder', ['title' => 'Employees', 'description' => 'Manage employee profiles, roles, and availability here.'])->name('employees.index');
        Route::view('/project-plan', 'stubs.placeholder', ['title' => 'Project Plan', 'description' => 'View and adjust the generated project plan.'])->name('project-plan.index');
        Route::view('/risks', 'stubs.placeholder', ['title' => 'Risks', 'description' => 'Track identified risks and mitigation strategies.'])->name('risks.index');
    });
});

require __DIR__.'/auth.php';
