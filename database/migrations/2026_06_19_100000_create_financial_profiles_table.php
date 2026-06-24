<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Taux de provisions appliqués aux encaissements (en %).
            $table->decimal('urssaf_rate', 5, 2)->default(22);
            $table->boolean('collects_vat')->default(false);
            $table->decimal('vat_rate', 5, 2)->nullable();
            // Provision impôt sur le revenu (0 si versement libératoire).
            $table->decimal('income_tax_rate', 5, 2)->default(0);

            $table->string('currency', 3)->default('EUR');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_profiles');
    }
};
