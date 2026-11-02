<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Country packs are shared templates; companies point at one. Numbers live in dated, approved versions.
        Schema::create('rule_packs', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->nullable(); // null = generic
            $table->string('code', 10)->unique();        // ID, GEN
            $table->string('name');
            $table->string('method');                    // ter_monthly_with_december_true_up | progressive_monthly
            $table->timestamps();
        });

        // Versions are never deleted. Only approved versions are used; the one in force is the latest effective_from ≤ period end.
        Schema::create('rule_pack_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_pack_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->date('effective_from');
            $table->string('status')->default('draft'); // draft | pending | approved | rejected | discarded
            $table->jsonb('config');
            $table->jsonb('sources');
            $table->string('change_note', 1000)->nullable();
            $table->foreignId('based_on_id')->nullable()->constrained('rule_pack_versions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['rule_pack_id', 'version']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('rule_pack_id')->references('id')->on('rule_packs')->nullOnDelete();
            $table->unsignedTinyInteger('pay_day')->default(25);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('bank_name', 60)->nullable();
            $table->text('bank_account')->nullable(); // encrypted
            $table->string('bank_account_last4', 4)->nullable();
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('kind')->default('earning'); // earning | deduction
            $table->boolean('taxable')->default(true);
            $table->boolean('fixed')->default(true);     // counts as wage for contributions, overtime and THR
            $table->boolean('recurring')->default(true); // monthly until ended, or a one-off in the month of effective_from
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 30);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->boolean('include_thr')->default(false);
            $table->string('status')->default('draft'); // draft | calculating | calculated | approved | locked | published
            $table->foreignId('rule_pack_version_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('progress')->default(0);
            $table->unsignedInteger('headcount')->default(0);
            $table->jsonb('totals')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_1')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at_1')->nullable();
            $table->foreignId('approved_by_2')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at_2')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'period_start']);
        });

        // Locked numbers are copies: changing salaries or rule packs later never alters them.
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            foreach (['gross', 'tax_bruto', 'tax', 'employee_contributions', 'employer_contributions', 'employee_deductible', 'other_deductions', 'net'] as $col) {
                $table->decimal($col, 14, 2);
            }
            $table->decimal('previous_net', 14, 2)->nullable();
            $table->jsonb('breakdown');
            $table->jsonb('tax_detail');
            $table->jsonb('flags');
            $table->jsonb('context'); // tax status, days, department, bank last4 at the time of calculation
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_components');
        Schema::dropIfExists('salary_components');
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['bank_name', 'bank_account', 'bank_account_last4']));
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['rule_pack_id']);
            $table->dropColumn('pay_day');
        });
        Schema::dropIfExists('rule_pack_versions');
        Schema::dropIfExists('rule_packs');
    }
};
