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
        Schema::table('projects', function (Blueprint $table) {
            $table->text('business_objective')->nullable()->after('description');
            $table->text('requirements_raw')->nullable()->after('business_objective');
            $table->date('start_date')->nullable()->after('requirements_raw');
            $table->string('requirement_analysis_status')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['business_objective', 'requirements_raw', 'start_date', 'requirement_analysis_status']);
        });
    }
};
