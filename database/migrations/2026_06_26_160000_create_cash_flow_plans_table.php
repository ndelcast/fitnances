<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('year');
            $table->bigInteger('starting_balance')->default(0);
            $table->boolean('irpf_exempt')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'year']);
        });

        Schema::create('cash_flow_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('cash_flow_plans')->cascadeOnDelete();
            $table->string('kind'); // income | expense | salary
            $table->string('label');
            $table->string('client_name')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'kind']);
        });

        Schema::create('cash_flow_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained('cash_flow_rows')->cascadeOnDelete();
            $table->unsignedTinyInteger('month'); // 1..12
            $table->bigInteger('amount')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['row_id', 'month']);
        });

        Schema::create('quarterly_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('cash_flow_plans')->cascadeOnDelete();
            $table->string('kind'); // iva | irpf
            $table->unsignedTinyInteger('quarter'); // 1..4
            $table->bigInteger('amount')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'kind', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_taxes');
        Schema::dropIfExists('cash_flow_cells');
        Schema::dropIfExists('cash_flow_rows');
        Schema::dropIfExists('cash_flow_plans');
    }
};
