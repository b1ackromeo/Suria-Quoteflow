<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('source_type', 80)->nullable()->after('related_document_id');
            $table->text('source_note')->nullable()->after('source_type');
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->string('category', 80)->default('supporting_document')->after('document_id');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_note']);
        });
    }
};
