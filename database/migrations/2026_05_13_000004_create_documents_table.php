<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'customer_quotation',
                'customer_po',
                'customer_invoice',
                'purchase_request',
                'supplier_quotation',
                'supplier_po',
                'goods_receipt',
                'supplier_invoice',
            ]);
            $table->enum('direction', ['outgoing', 'incoming']);
            $table->string('document_number', 40);
            $table->string('external_reference')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('related_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'rejected',
                'issued',
                'fulfilled',
                'received',
                'matched',
                'part_paid',
                'paid',
                'closed',
                'cancelled',
            ])->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'document_number']);
            $table->index(['type', 'status', 'issue_date']);
            $table->index(['direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
