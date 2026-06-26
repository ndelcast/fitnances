<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->dropColumn(['urssaf_rate', 'collects_vat', 'vat_rate', 'income_tax_rate']);
        });

        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('user_id');
            $table->string('nif', 20)->nullable()->after('full_name');
            $table->string('activity')->nullable()->after('nif');
            $table->string('province')->nullable()->after('activity');
            $table->string('regime')->default('direct_simplified')->after('province');
            $table->decimal('iva_default', 5, 2)->default(21)->after('regime');
            $table->decimal('irpf_default', 5, 2)->default(15)->after('iva_default');
            $table->bigInteger('cuota_monthly')->default(0)->after('irpf_default');
            $table->boolean('surcharge_equivalence')->default(false)->after('cuota_monthly');
            $table->boolean('intra_community')->default(false)->after('surcharge_equivalence');
        });
    }

    public function down(): void
    {
        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'full_name', 'nif', 'activity', 'province', 'regime',
                'iva_default', 'irpf_default', 'cuota_monthly',
                'surcharge_equivalence', 'intra_community',
            ]);
        });

        Schema::table('financial_profiles', function (Blueprint $table) {
            $table->decimal('urssaf_rate', 5, 2)->default(22);
            $table->boolean('collects_vat')->default(false);
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('income_tax_rate', 5, 2)->default(0);
        });
    }
};
