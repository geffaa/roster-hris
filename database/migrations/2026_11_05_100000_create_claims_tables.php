<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->decimal('monthly_limit', 14, 2)->nullable(); // per person per calendar month; null = no limit
            $table->boolean('receipt_required')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_category_id')->constrained()->cascadeOnDelete();
            $table->date('spent_on');
            $table->decimal('amount', 14, 2);
            $table->string('description', 300);
            $table->string('receipt_path')->nullable(); // private disk
            $table->string('status')->default('pending'); // pending | approved | rejected | cancelled | paid
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete(); // the run that paid it
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
        Schema::dropIfExists('claim_categories');
    }
};
