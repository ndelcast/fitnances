<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->bigInteger('monthly_salary')->default(0)->after('cuota_monthly');
        });
    }

    public function down(): void
    {
        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->dropColumn('monthly_salary');
        });
    }
};
