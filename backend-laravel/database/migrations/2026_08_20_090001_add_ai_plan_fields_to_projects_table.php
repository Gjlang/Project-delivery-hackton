<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('ai_generated_plan')->nullable()->after('project_characteristics');
            $table->foreignId('recommended_employee_id')->nullable()->after('ai_generated_plan')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recommended_employee_id');
            $table->dropColumn('ai_generated_plan');
        });
    }
};
