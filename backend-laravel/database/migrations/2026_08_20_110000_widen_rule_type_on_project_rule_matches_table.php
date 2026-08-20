<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `rule_type` was sized for the old short category prefix (BR/CP/EW/SC/TS/AG,
 * max ~3 chars, hence varchar(10)). The LangGraph confirm() flow now stores
 * the full category/table name instead (e.g. "business_rules",
 * "security_compliance") -- widen the column to fit those.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (the test suite's driver) doesn't support ALTER ... MODIFY,
        // and doesn't enforce varchar length limits in the first place, so
        // this widening is a MySQL-only concern.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_rule_matches MODIFY rule_type VARCHAR(30) NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_rule_matches MODIFY rule_type VARCHAR(10) NOT NULL');
        }
    }
};
