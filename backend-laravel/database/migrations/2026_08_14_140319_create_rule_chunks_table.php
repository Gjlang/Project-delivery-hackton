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
        Schema::create('rule_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_rule_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('chunk_text');
            $table->unsignedInteger('token_count')->nullable();

            // Preparation only for the future embedding phase -- this table
            // is never populated or connected to Qdrant in this task.
            $table->string('embedding_status')->default('pending');
            $table->string('external_vector_id')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('company_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rule_chunks');
    }
};
