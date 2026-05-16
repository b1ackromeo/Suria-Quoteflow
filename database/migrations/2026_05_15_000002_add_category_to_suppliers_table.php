<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('category', 120)->nullable()->index();
        });

        DB::table('suppliers')->where('code', 'BESTSUP')->whereNull('category')->update(['category' => 'Materials / Hardware']);
        DB::table('suppliers')->where('code', 'METRO')->whereNull('category')->update(['category' => 'Office / Admin']);
        DB::table('suppliers')->where('code', 'PRIMELOG')->whereNull('category')->update(['category' => 'Logistics / Delivery']);
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
