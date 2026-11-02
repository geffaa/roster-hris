<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->char('country_code', 2);
            $table->char('base_currency', 3);
            $table->string('timezone');
            $table->string('locale', 10)->default('en');
            $table->unsignedBigInteger('rule_pack_id')->nullable(); // FK added in M3 with rule_packs
            $table->string('proration_policy')->default('calendar_days'); // calendar_days | working_days
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_company_id')->nullable()->after('password')->constrained('companies')->nullOnDelete();
            $table->text('two_factor_secret')->nullable()->after('current_company_id');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_confirmed_at');
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->unsignedInteger('radius_m')->default(150);
            $table->boolean('allow_remote')->default(false);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('cost_center')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
        Schema::dropIfExists('locations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_company_id');
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'last_login_at']);
        });
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
