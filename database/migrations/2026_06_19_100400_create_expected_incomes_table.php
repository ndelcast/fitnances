<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expected_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('label');
            $table->string('client_name')->nullable();
            // Montant en centimes.
            $table->unsignedBigInteger('amount');
            $table->date('expected_on');
            // 'pending' | 'received' | 'late' (cf. App\Enums\ExpectedIncomeStatus).
            $table->string('status')->default('pending');
            // Renseignée quand la facture est encaissée et liée à une transaction.
            $table->foreignId('received_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'expected_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expected_incomes');
    }
};
