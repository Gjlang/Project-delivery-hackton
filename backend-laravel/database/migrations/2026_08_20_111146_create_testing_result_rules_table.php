<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "TR" (Testing Results) rule category -- rules describing what a
     * passing Playwright test run must look like, checked against a
     * project's actual WebsiteTestRun results in Phase 5. Same shape as the
     * other 6 rule-category tables, but created_by is required from the
     * start (rules are per-user for every category now).
     */
    public function up(): void
    {
        Schema::create('testing_result_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rule_code');
            $table->string('section')->nullable();
            $table->string('title');
            $table->text('rule_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['created_by', 'rule_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testing_result_rules');
    }
};
