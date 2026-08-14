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
        Schema::table('documents', function (Blueprint $table) {
            $table->json('extracted_rules')->nullable()->after('extracted_sections');
            $table->json('keyword_hits')->nullable()->after('extracted_rules');
            $table->unsignedInteger('word_count')->default(0)->after('keyword_hits');
            $table->text('parse_error')->nullable()->after('word_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['extracted_rules', 'keyword_hits', 'word_count', 'parse_error']);
        });
    }
};
