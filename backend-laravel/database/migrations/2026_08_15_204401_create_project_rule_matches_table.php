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

            // Rules now live in separate per-category tables (business_rules,
            // company_policies, employee_rules, security_compliance,
            // technical_standards, approval_governance -- see
            // config/knowledge_rules.php) instead of one unified company_rules
            // table, so there's no single id space to foreign-key against.
            // rule_type stores the category prefix (BR/CP/EW/SC/TS/AG) and
            // rule_id is that category table's own primary key.
            $table->string('rule_type', 10);
            $table->unsignedBigInteger('rule_id');
            $table->string('rule_code')->nullable();

            $table->string('context')->nullable();
            $table->string('decision');
            $table->decimal('similarity_score', 6, 4)->nullable();
            $table->text('reason')->nullable();
            $table->string('source_reference')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'project_id']);
            $table->index(['rule_type', 'rule_id']);
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
