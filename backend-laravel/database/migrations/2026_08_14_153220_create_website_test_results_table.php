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
        Schema::create('website_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_test_run_id')->constrained()->cascadeOnDelete();

            $table->string('rule_code');
            $table->string('category', 10);

            // Rule applicability (internal) vs test status (official) are
            // deliberately separate columns -- see section 24 of the spec.
            $table->string('applicability_status'); // applicable | not_applicable | insufficient_context | policy_rule | not_browser_testable
            $table->string('status'); // PASS | WARNING | FAIL | NOT_TESTABLE

            $table->string('tested_page')->nullable();

            $table->text('expected_behavior')->nullable();
            $table->text('observed_behavior')->nullable();

            $table->string('severity')->nullable(); // critical | high | medium | low

            $table->float('similarity_score')->nullable();
            $table->unsignedInteger('execution_duration_ms')->nullable();

            $table->json('evidence')->nullable();

            $table->text('ai_explanation')->nullable();
            $table->text('ai_impact')->nullable();
            $table->text('ai_recommendation')->nullable();

            $table->timestamps();

            $table->index(['website_test_run_id', 'status']);
            $table->index(['website_test_run_id', 'rule_code']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_test_results');
    }
};
