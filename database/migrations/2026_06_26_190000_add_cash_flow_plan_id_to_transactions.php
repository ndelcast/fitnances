<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('cash_flow_plan_id')
                ->nullable()
                ->after('user_id')
                ->constrained('cash_flow_plans')
                ->cascadeOnDelete();

            $table->index(['user_id', 'cash_flow_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'cash_flow_plan_id']);
            $table->dropConstrainedForeignId('cash_flow_plan_id');
        });
    }
};
