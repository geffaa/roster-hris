<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('crosses_midnight')->default(false);
            $table->unsignedSmallInteger('grace_min')->default(10);
            $table->json('working_days'); // ISO weekdays 1 (Mon) … 7 (Sun)
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('work_pattern_id')->references('id')->on('work_patterns')->nullOnDelete();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('region')->nullable();
            $table->date('date');
            $table->string('name');
            $table->boolean('collective')->default(false); // Indonesian cuti bersama
            $table->timestamps();
            $table->unique(['country_code', 'region', 'date', 'name']);
            $table->index(['country_code', 'date']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // leave | attendance_correction | overtime
            $table->morphs('subject');
            $table->foreignId('requester_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | approved | rejected | cancelled
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->foreignId('approver_id')->nullable()->constrained('employees')->nullOnDelete(); // null = HR queue
            $table->foreignId('delegated_from_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status')->default('waiting'); // waiting | pending | approved | rejected | skipped
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['approval_request_id', 'step']);
            $table->index(['approver_id', 'status']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->timestampTz('clock_in_at');
            $table->timestampTz('clock_out_at')->nullable();
            $table->decimal('in_lat', 9, 6)->nullable();
            $table->decimal('in_lng', 9, 6)->nullable();
            $table->decimal('out_lat', 9, 6)->nullable();
            $table->decimal('out_lng', 9, 6)->nullable();
            $table->unsignedInteger('in_distance_m')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('remote')->default(false);
            $table->string('status')->default('ok'); // ok | late | incomplete | corrected
            $table->unsignedSmallInteger('late_min')->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']); // one shift per day in M2
            $table->index(['company_id', 'work_date']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('employees')->cascadeOnDelete();
            $table->timestampTz('original_in')->nullable();
            $table->timestampTz('original_out')->nullable();
            $table->timestampTz('new_in');
            $table->timestampTz('new_out');
            $table->string('reason', 500);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('rule_pack_id')->nullable(); // linked to rule packs in M3
            $table->string('code');
            $table->string('name');
            $table->boolean('paid')->default(true);
            $table->decimal('annual_days', 5, 1)->nullable(); // null = not balance-limited (e.g. sick leave)
            $table->string('accrual')->default('upfront'); // upfront | monthly
            $table->unsignedSmallInteger('min_service_months')->default(0);
            $table->decimal('max_carry_over', 5, 1)->default(0);
            $table->boolean('allow_negative')->default(false);
            $table->boolean('half_day_allowed')->default(true);
            $table->boolean('requires_second_approval')->default(false);
            $table->string('color', 7)->default('#7FA7D9');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('carried_over', 5, 1)->default(0);
            $table->decimal('adjustment', 5, 1)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('half_day')->default('none'); // none | am | pm
            $table->decimal('days', 5, 1);
            $table->string('reason', 500)->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected | cancelled
            $table->date('original_end_date')->nullable(); // set when an approved leave is cut short
            $table->timestamps();
            $table->index(['company_id', 'start_date', 'end_date']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('minutes');
            $table->string('reason', 500);
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('holidays');
        Schema::table('contracts', fn (Blueprint $table) => $table->dropForeign(['work_pattern_id']));
        Schema::dropIfExists('work_patterns');
    }
};
