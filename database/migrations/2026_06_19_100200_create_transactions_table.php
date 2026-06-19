<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // 'income' ou 'expense' (cf. App\Enums\TransactionType).
            $table->string('type');
            // Montant en centimes, tel qu'il touche le compte (TTC si TVA).
            $table->unsignedBigInteger('amount');
            $table->string('label');
            $table->date('occurred_on');
            // 'manual' ou 'csv' (cf. App\Enums\TransactionSource).
            $table->string('source')->default('manual');

            $table->timestamps();

            $table->index(['user_id', 'occurred_on']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
