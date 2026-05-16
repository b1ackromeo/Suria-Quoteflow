<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachment_extractions', function (Blueprint $table) {
            $table->string('verification_method', 40)->nullable()->after('verified_fields');
            $table->text('verification_notes')->nullable()->after('verification_method');
            $table->boolean('supplier_confirmed')->default(false)->after('verification_notes');
            $table->boolean('recorded_total_confirmed')->default(false)->after('supplier_confirmed');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index(['type', 'supplier_id', 'external_reference'], 'documents_supplier_invoice_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_supplier_invoice_reference_idx');
        });

        Schema::table('attachment_extractions', function (Blueprint $table) {
            $table->dropColumn([
                'verification_method',
                'verification_notes',
                'supplier_confirmed',
                'recorded_total_confirmed',
            ]);
        });
    }
};
