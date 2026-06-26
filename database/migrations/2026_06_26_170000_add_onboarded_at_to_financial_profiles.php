<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable()->after('intra_community');
        });

        // Profils déjà renseignés (cuota ou salaire) : considérés onboardés.
        DB::table('financial_profiles')
            ->where(function ($query) {
                $query->where('cuota_monthly', '>', 0)
                    ->orWhere('monthly_salary', '>', 0);
            })
            ->update(['onboarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->dropColumn('onboarded_at');
        });
    }
};
