<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per TR rule evaluated against a specific WebsiteTestRun's
     * results, by TestingResultRuleValidationService. Phase 5's markDone
     * gate checks the latest batch (by website_test_run_id) for this
     * project has no FAIL/NEEDS_INFORMATION rows.
     */
    public function up(): void
    {
        Schema::create('testing_result_rule_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_test_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->string('rule_code');
            $table->string('title')->nullable();
            $table->string('status'); // PASS | NEEDS_INFORMATION | FAIL | NOT_APPLICABLE
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['website_test_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testing_result_rule_checks');
    }
};
