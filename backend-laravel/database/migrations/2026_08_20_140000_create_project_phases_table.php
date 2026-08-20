<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('phase_number');
            $table->string('phase_name');
            $table->unsignedInteger('duration_days')->nullable();
            $table->text('duration_reason')->nullable();

            // TaskPlan[] from the AI-generated plan: [{name, description, duration_days}, ...]
            $table->json('tasks')->nullable();

            $table->string('status')->default('not_started'); // not_started | in_progress | done

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'phase_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phases');
    }
};
