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
        Schema::create('rule_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_rule_id')->constrained()->cascadeOnDelete();

            $table->string('parameter_key');
            $table->string('parameter_value');
            $table->string('value_type')->default('string');
            $table->string('unit')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('company_rule_id');
            $table->index('parameter_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rule_parameters');
    }
};
