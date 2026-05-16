<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('project_name')->nullable()->after('related_document_id');
            $table->text('delivery_to')->nullable()->after('project_name');
            $table->string('payment_terms_type', 40)->default('standard')->after('currency');
            $table->unsignedSmallInteger('payment_due_days')->nullable()->after('payment_terms_type');
            $table->string('payment_terms_label')->nullable()->after('payment_due_days');
            $table->unsignedSmallInteger('progress_invoice_number')->nullable()->after('payment_terms_label');
            $table->unsignedSmallInteger('progress_invoice_total')->nullable()->after('progress_invoice_number');
            $table->string('billing_stage_name')->nullable()->after('progress_invoice_total');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'project_name',
                'delivery_to',
                'payment_terms_type',
                'payment_due_days',
                'payment_terms_label',
                'progress_invoice_number',
                'progress_invoice_total',
                'billing_stage_name',
            ]);
        });
    }
};
