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
        Schema::create('company_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_document_id')->nullable()->constrained('knowledge_documents')->nullOnDelete();
            $table->foreignId('supersedes_rule_id')->nullable()->constrained('company_rules')->nullOnDelete();

            $table->string('rule_code');
            $table->string('title');
            $table->text('rule_text');

            $table->string('section')->nullable();
            $table->string('subsection')->nullable();

            $table->text('applicable_condition')->nullable();
            $table->text('required_behavior')->nullable();
            $table->text('expected_outcome')->nullable();

            $table->string('rule_type')->nullable();
            $table->string('evaluation_type')->nullable();

            $table->string('severity')->nullable();
            $table->string('priority')->nullable();

            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);

            $table->string('version')->default('1.0');
            $table->string('status')->default('active');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            $table->json('metadata')->nullable();

            $table->string('source_reference')->nullable();
            $table->unsignedInteger('source_page')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'rule_code', 'version'], 'company_rule_code_version_unique');
            $table->index('rule_code');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'rule_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_rules');
    }
};
