<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('currency_display', 40)->nullable()->after('base_currency');
            $table->string('currency_symbol_override', 20)->nullable()->after('currency_display');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'currency_display',
                'currency_symbol_override',
            ]);
        });
    }
};
