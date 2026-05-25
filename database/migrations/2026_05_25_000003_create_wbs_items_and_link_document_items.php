<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbs_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('wbs_items')->nullOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cost_type', 40)->default('service');
            $table->decimal('revenue_budget', 15, 2)->default(0);
            $table->decimal('cost_budget', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 40)->default('active');
            $table->timestamps();

            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'status', 'sort_order']);
            $table->index('parent_id');
        });

        Schema::table('document_items', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('product_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('wbs_item_id')
                ->nullable()
                ->after('project_id')
                ->constrained('wbs_items')
                ->nullOnDelete();

            $table->index(['project_id', 'wbs_item_id']);
        });

        DB::statement('
            update document_items
            set project_id = (
                select documents.project_id
                from documents
                where documents.id = document_items.document_id
            )
            where project_id is null
              and exists (
                select 1
                from documents
                where documents.id = document_items.document_id
                  and documents.project_id is not null
              )
        ');
    }

    public function down(): void
    {
        Schema::table('document_items', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'wbs_item_id']);
            $table->dropConstrainedForeignId('wbs_item_id');
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::dropIfExists('wbs_items');
    }
};
