<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->decimal('retention_percent', 5, 2)->nullable()->after('billing_stage_name');
            $table->decimal('retention_amount', 15, 2)->nullable()->after('retention_percent');
            $table->date('retention_release_date')->nullable()->after('retention_amount');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'retention_percent',
                'retention_amount',
                'retention_release_date',
            ]);
        });
    }
};
