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
        Schema::create('rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_rule_id')->constrained()->cascadeOnDelete();

            $table->string('field');
            $table->string('operator');
            $table->text('value')->nullable();
            $table->string('value_type')->nullable();

            $table->string('condition_group')->nullable();
            $table->string('logical_operator')->nullable();

            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('company_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rule_conditions');
    }
};
