<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_flow_rows', function (Blueprint $table) {
            $table->boolean('has_irpf')->default(false)->after('has_iva');
        });
    }

    public function down(): void
    {
        Schema::table('cash_flow_rows', function (Blueprint $table) {
            $table->dropColumn('has_irpf');
        });
    }
};
