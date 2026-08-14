<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('requirement_type');

            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->text('source_text')->nullable();

            $table->string('priority')->nullable();
            $table->boolean('is_mandatory')->default(false);

            $table->string('status')->default('identified');
            $table->text('clarification_reason')->nullable();
            $table->text('clarification_question')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'requirement_type']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_requirements');
    }
};
