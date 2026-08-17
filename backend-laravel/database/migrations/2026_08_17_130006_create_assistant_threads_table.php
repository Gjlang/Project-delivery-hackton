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
        Schema::create('assistant_threads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Nullable: a thread is created at login, which can happen before
            // a brand-new user has set up (or joined) a company.
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_threads');
    }
};
