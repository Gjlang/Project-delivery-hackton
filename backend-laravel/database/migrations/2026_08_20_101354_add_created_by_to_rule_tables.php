<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rules go from global/shared to per-user: each of the 6 rule-category
     * tables gets a created_by owner column, and rule_code (previously
     * globally unique -- one shared "BR-001") becomes unique per user
     * instead, since two different users' independently-uploaded rulebooks
     * will very likely reuse the same codes.
     */
    private array $tables = [
        'business_rules',
        'company_policies',
        'employee_rules',
        'security_compliance',
        'technical_standards',
        'approval_governance',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique($table.'_rule_code_unique');
                $blueprint->foreignId('created_by')->nullable()->after('id')
                    ->constrained('users')->nullOnDelete();
                $blueprint->unique(['created_by', 'rule_code']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['created_by', 'rule_code']);
                $blueprint->dropConstrainedForeignId('created_by');
                $blueprint->unique('rule_code');
            });
        }
    }
};
