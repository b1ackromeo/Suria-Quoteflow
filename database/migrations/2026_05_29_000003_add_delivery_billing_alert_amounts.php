<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->decimal('delivery_gap_alert_amount', 15, 2)->default(0)->after('received_not_invoiced_alert_percent');
            $table->decimal('received_not_invoiced_alert_amount', 15, 2)->default(0)->after('delivery_gap_alert_amount');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('delivery_gap_alert_amount', 15, 2)->nullable()->after('received_not_invoiced_alert_percent');
            $table->decimal('received_not_invoiced_alert_amount', 15, 2)->nullable()->after('delivery_gap_alert_amount');
        });

        Schema::table('wbs_items', function (Blueprint $table) {
            $table->decimal('delivery_gap_alert_amount', 15, 2)->nullable()->after('received_not_invoiced_alert_percent');
            $table->decimal('received_not_invoiced_alert_amount', 15, 2)->nullable()->after('delivery_gap_alert_amount');
        });
    }

    public function down(): void
    {
        Schema::table('wbs_items', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_gap_alert_amount',
                'received_not_invoiced_alert_amount',
            ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_gap_alert_amount',
                'received_not_invoiced_alert_amount',
            ]);
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_gap_alert_amount',
                'received_not_invoiced_alert_amount',
            ]);
        });
    }
};
