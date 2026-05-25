<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 80)->unique();
            $table->string('name');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('active');
            $table->date('start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->decimal('contract_value', 15, 2)->default(0);
            $table->decimal('budget_amount', 15, 2)->default(0);
            $table->decimal('margin_target_percent', 5, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['status', 'expected_completion_date']);
            $table->index('customer_id');
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
