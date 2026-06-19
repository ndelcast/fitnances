<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('label');
            // Montant en centimes.
            $table->unsignedBigInteger('amount');
            // 'weekly' | 'monthly' | 'quarterly' | 'yearly' (cf. App\Enums\ChargeFrequency).
            $table->string('frequency');
            $table->date('next_due_on');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'next_due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_charges');
    }
};
