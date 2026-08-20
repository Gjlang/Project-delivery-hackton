<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "CP" (Company Rules and Policies) is dropped -- confirmed unused
     * anywhere in the app (never read by any graph node, validation
     * service, or controller besides its own listing/upload plumbing,
     * which is generic over config('knowledge_rules') and needed no
     * changes once the config entry was removed).
     */
    public function up(): void
    {
        Schema::dropIfExists('company_policies');
    }

    public function down(): void
    {
        Schema::create('company_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rule_code');
            $table->string('section')->nullable();
            $table->string('title');
            $table->text('rule_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['created_by', 'rule_code']);
        });
    }
};
