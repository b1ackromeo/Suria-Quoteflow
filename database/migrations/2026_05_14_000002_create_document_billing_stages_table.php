<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_billing_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('stage_name');
            $table->string('condition_label')->nullable();
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('payment_term')->nullable();
            $table->decimal('previously_invoiced', 15, 2)->default(0);
            $table->decimal('current_invoice', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->index(['document_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_billing_stages');
    }
};
