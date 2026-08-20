<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * company_id is no longer the access-control boundary (created_by /
     * user_id is), and a user's company_id can legitimately be null -- so
     * it can no longer be a required NOT NULL FK on rows a user creates.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
        });

        Schema::table('project_creation_sessions', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
        });

        Schema::table('project_rule_matches', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
        });

        Schema::table('website_test_runs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });

        Schema::table('project_creation_sessions', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });

        Schema::table('project_rule_matches', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });

        Schema::table('website_test_runs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });
    }
};
