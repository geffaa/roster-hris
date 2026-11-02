<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_no');
            $table->string('full_name');
            $table->string('preferred_name')->nullable();
            $table->string('job_title');
            $table->string('work_email')->nullable();
            $table->date('hire_date');
            $table->date('end_date')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active'); // active | on_leave | resigned
            $table->text('personal_data')->nullable(); // encrypted JSON: birth date, phone, address, emergency contact
            $table->timestamps();
            $table->unique(['company_id', 'employee_no']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('employee_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // nik | npwp | bpjs_kes | bpjs_tk | ssn | ni | tin | other
            $table->text('value'); // encrypted
            $table->string('value_last4', 4);
            $table->timestamps();
            $table->unique(['employee_id', 'type']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // permanent | fixed_term | intern | part_time
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('work_pattern_id')->nullable(); // FK added in M2 with work_patterns
            $table->timestamps();
        });

        Schema::create('compensation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('base_salary', 14, 2);
            $table->char('currency', 3);
            $table->string('pay_frequency')->default('monthly');
            $table->string('tax_status')->nullable(); // PTKP code (e.g. K/1) or filing status
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('path');
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::dropIfExists('documents');
        Schema::dropIfExists('compensation_history');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('employee_identifiers');
        Schema::dropIfExists('employees');
    }
};
