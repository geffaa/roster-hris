<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A requested bank account; it replaces the one on the employee only after HR approves.
        Schema::create('bank_account_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name', 60);
            $table->text('account_number'); // encrypted
            $table->string('account_last4', 4);
            $table->string('holder_name', 120);
            $table->string('status')->default('pending'); // pending | approved | rejected | cancelled
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        Schema::table('users', fn (Blueprint $table) => $table->string('locale', 5)->default('en'));
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_changes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
