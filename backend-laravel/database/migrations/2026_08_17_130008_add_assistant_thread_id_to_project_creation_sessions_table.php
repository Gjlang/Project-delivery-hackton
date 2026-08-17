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
        Schema::table('project_creation_sessions', function (Blueprint $table) {
            $table->foreignId('assistant_thread_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_creation_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assistant_thread_id');
        });
    }
};
