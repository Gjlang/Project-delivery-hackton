<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_governance', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code')->unique();
            $table->string('section')->nullable();
            $table->string('title');
            $table->text('rule_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_governance');
    }
};
