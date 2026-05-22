<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('country', 120)->default('Other')->after('accent_color');
            $table->string('timezone', 120)->default('UTC')->after('country');
            $table->string('base_currency', 3)->default('USD')->after('timezone');
            $table->string('date_format', 40)->default('Y-m-d')->after('base_currency');
            $table->string('number_format', 40)->default('en-US')->after('date_format');
            $table->string('tax_label', 80)->default('Tax')->after('number_format');
            $table->string('tax_registration_number', 120)->nullable()->after('tax_label');
            $table->decimal('default_tax_rate', 5, 2)->default(0)->after('tax_registration_number');
            $table->text('payment_instructions')->nullable()->after('default_tax_rate');
            $table->text('pdf_footer')->nullable()->after('payment_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'timezone',
                'base_currency',
                'date_format',
                'number_format',
                'tax_label',
                'tax_registration_number',
                'default_tax_rate',
                'payment_instructions',
                'pdf_footer',
            ]);
        });
    }
};
