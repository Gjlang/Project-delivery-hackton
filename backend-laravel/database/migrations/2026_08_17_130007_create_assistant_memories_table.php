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
        Schema::create('assistant_memories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assistant_thread_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('source')->nullable();

            // Memories are immutable facts -- a correction is a new row, not
            // an edit, so there's no updated_at to maintain.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['assistant_thread_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_memories');
    }
};
