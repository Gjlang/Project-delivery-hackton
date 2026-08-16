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
        Schema::create('project_creation_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('active');
            $table->string('analysis_status')->default('gathering');
            $table->string('rules_status')->nullable();

            $table->json('draft_data')->nullable();
            $table->json('decision_progress')->nullable();
            $table->json('clarifications')->nullable();

            $table->foreignId('confirmed_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'user_id', 'status']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_creation_sessions');
    }
};
