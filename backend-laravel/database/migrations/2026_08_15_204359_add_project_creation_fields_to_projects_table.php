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
            $table->string('primary_project_type')->nullable()->after('status');
            $table->json('project_characteristics')->nullable()->after('primary_project_type');
            $table->string('creation_source')->default('legacy_form')->after('project_characteristics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['primary_project_type', 'project_characteristics', 'creation_source']);
        });
    }
};
