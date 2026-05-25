<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('related_document_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['project_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'type', 'status']);
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
