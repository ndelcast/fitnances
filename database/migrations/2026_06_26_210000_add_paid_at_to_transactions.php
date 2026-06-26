<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('occurred_on');
            $table->index(['user_id', 'paid_at']);
        });

        // Backfill : les transactions passées sont considérées comme déjà cobradas.
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE transactions SET paid_at = occurred_on WHERE occurred_on <= CURRENT_DATE'
        );
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
