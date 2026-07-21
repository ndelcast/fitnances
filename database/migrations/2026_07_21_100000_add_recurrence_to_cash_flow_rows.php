<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_flow_rows', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('has_irpf');
            $table->unsignedTinyInteger('recurrence_interval')->nullable()->after('is_recurring');
            $table->unsignedTinyInteger('recurrence_start_month')->nullable()->after('recurrence_interval');
            $table->unsignedTinyInteger('recurrence_end_month')->nullable()->after('recurrence_start_month');
        });
    }

    public function down(): void
    {
        Schema::table('cash_flow_rows', function (Blueprint $table) {
            $table->dropColumn([
                'is_recurring',
                'recurrence_interval',
                'recurrence_start_month',
                'recurrence_end_month',
            ]);
        });
    }
};
