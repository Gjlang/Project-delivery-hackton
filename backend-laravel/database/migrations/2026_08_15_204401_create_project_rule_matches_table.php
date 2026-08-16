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
        Schema::create('project_rule_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_rule_id')->constrained()->cascadeOnDelete();

            $table->string('context')->nullable();
            $table->string('decision');
            $table->decimal('similarity_score', 6, 4)->nullable();
            $table->text('reason')->nullable();
            $table->string('source_reference')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'project_id']);
            $table->index(['company_rule_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_rule_matches');
    }
};
