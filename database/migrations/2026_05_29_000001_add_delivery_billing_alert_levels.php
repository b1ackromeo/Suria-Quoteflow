<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->decimal('delivery_gap_alert_percent', 5, 2)->default(25)->after('default_tax_rate');
            $table->decimal('received_not_invoiced_alert_percent', 5, 2)->default(10)->after('delivery_gap_alert_percent');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('delivery_gap_alert_percent', 5, 2)->nullable()->after('margin_target_percent');
            $table->decimal('received_not_invoiced_alert_percent', 5, 2)->nullable()->after('delivery_gap_alert_percent');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_gap_alert_percent',
                'received_not_invoiced_alert_percent',
            ]);
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_gap_alert_percent',
                'received_not_invoiced_alert_percent',
            ]);
        });
    }
};
