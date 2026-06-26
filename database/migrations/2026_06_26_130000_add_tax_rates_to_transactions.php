<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('iva_rate', 5, 2)->nullable()->after('amount');
            $table->decimal('irpf_rate', 5, 2)->nullable()->after('iva_rate');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['iva_rate', 'irpf_rate']);
        });
    }
};
