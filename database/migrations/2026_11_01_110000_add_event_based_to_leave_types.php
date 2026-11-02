<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            // Granted for a life event (marriage, birth, bereavement) rather than accrued as a yearly balance.
            $table->boolean('event_based')->default(false)->after('accrual');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', fn (Blueprint $table) => $table->dropColumn('event_based'));
    }
};
