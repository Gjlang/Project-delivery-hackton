<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('role');
            $table->json('skills')->nullable();
            $table->enum('skill_level', ['Junior', 'Intermediate', 'Senior'])->default('Junior');
            $table->unsignedInteger('active_project_count')->default(0);
            $table->enum('status', ['active', 'inactive', 'leave'])->default('active');

            $table->timestamps();

            $table->index(['company_id', 'role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
