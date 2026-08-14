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
        Schema::create('website_test_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('website_url');
            $table->string('status')->default('pending');

            $table->json('browser_configuration')->nullable();

            // Test credentials/passwords are deliberately redacted before
            // this is ever written -- see WebsiteTestingService::redact().
            $table->json('test_context_snapshot')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('total_rules')->default(0);
            $table->unsignedInteger('executed_tests')->default(0);
            $table->unsignedInteger('passed')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('not_testable')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_test_runs');
    }
};
