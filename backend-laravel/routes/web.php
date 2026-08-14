<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/company-knowledge', [DocumentController::class, 'index'])->name('company-knowledge.index');
    Route::get('/company-knowledge/library', [DocumentController::class, 'library'])->name('company-knowledge.library');
    Route::post('/company-knowledge/documents', [DocumentController::class, 'store'])->name('company-knowledge.documents.store');
    Route::get('/company-knowledge/documents/{document}', [DocumentController::class, 'show'])->name('company-knowledge.documents.show');
    Route::put('/company-knowledge/documents/{document}', [DocumentController::class, 'update'])->name('company-knowledge.documents.update');
    Route::post('/company-knowledge/documents/{document}/reindex', [DocumentController::class, 'reindex'])->name('company-knowledge.documents.reindex');
    Route::delete('/company-knowledge/documents/{document}', [DocumentController::class, 'destroy'])->name('company-knowledge.documents.destroy');

    Route::view('/employees', 'stubs.placeholder', ['title' => 'Employees', 'description' => 'Manage employee profiles, roles, and availability here.'])->name('employees.index');
    Route::view('/projects/new', 'stubs.placeholder', ['title' => 'New Project', 'description' => 'Kick off a new project and let AI draft the initial plan.'])->name('projects.new');
    Route::get('/ai-analysis', [DocumentController::class, 'summary'])->name('ai-analysis.index');
    Route::view('/project-plan', 'stubs.placeholder', ['title' => 'Project Plan', 'description' => 'View and adjust the generated project plan.'])->name('project-plan.index');
    Route::view('/risks', 'stubs.placeholder', ['title' => 'Risks', 'description' => 'Track identified risks and mitigation strategies.'])->name('risks.index');
});

require __DIR__.'/auth.php';
