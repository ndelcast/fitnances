<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute `issued_on` (date d'émission de la facture) aux movements.
 *
 * Sémantique des 3 dates :
 *  - issued_on    : date d'émission (factura), nullable → utile pour le DSO
 *  - estimated_on : date prévue d'impact cash (planification)
 *  - paid_at      : date réelle d'encaissement / paiement
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->date('issued_on')->nullable()->after('estimated_on');
            $table->index(['user_id', 'issued_on']);
        });
    }

    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'issued_on']);
            $table->dropColumn('issued_on');
        });
    }
};
