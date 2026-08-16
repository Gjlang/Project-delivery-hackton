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
        Schema::create('project_creation_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')->constrained('project_creation_sessions')->cascadeOnDelete();

            $table->string('role');
            $table->text('content');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_creation_messages');
    }
};
