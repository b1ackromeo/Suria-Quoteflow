<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_variations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('variation_number', 80);
            $table->string('title');
            $table->string('status', 40)->default('pending_review');
            $table->date('effective_date')->nullable();
            $table->decimal('customer_value', 15, 2)->default(0);
            $table->decimal('supplier_cost', 15, 2)->default(0);
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'variation_number']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_variations');
    }
};
