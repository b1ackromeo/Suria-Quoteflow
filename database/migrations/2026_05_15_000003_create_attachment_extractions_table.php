<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('pending');
            $table->string('engine', 80)->default('tesseract');
            $table->string('language', 20)->default('eng');
            $table->longText('raw_text')->nullable();
            $table->json('extracted_fields')->nullable();
            $table->json('verified_fields')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_extractions');
    }
};
