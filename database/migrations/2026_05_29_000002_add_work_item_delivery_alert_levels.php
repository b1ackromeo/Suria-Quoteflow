<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wbs_items', function (Blueprint $table) {
            $table->decimal('delivery_gap_alert_percent', 5, 2)->nullable()->after('cost_budget');
            $table->decimal('received_not_invoiced_alert_percent', 5, 2)->nullable()->after('delivery_gap_alert_percent');
        });
    }

    public function down(): void
    {
        Schema::table('wbs_items', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_gap_alert_percent',
                'received_not_invoiced_alert_percent',
            ]);
        });
    }
};
