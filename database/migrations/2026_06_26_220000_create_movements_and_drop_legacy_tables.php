<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration consolidée vers un modèle unifié `Movement`.
 *
 * Crée la table `movements` qui remplace 5 modèles distincts :
 *  - transactions
 *  - cash_flow_cells
 *  - expected_incomes
 *  - recurring_charges
 *  - quarterly_taxes
 *
 * Les données existantes sont migrées dans une transaction unique,
 * puis les anciennes tables sont droppées.
 *
 * Migration one-way : `down()` ne restaure pas les données (drop des
 * tables d'origine). Acceptable en pre-prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_flow_plan_id')->nullable()->constrained('cash_flow_plans')->cascadeOnDelete();
            $table->foreignId('cash_flow_row_id')->nullable()->constrained('cash_flow_rows')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('kind'); // income | expense | salary | tax
            $table->string('label');
            $table->string('client_name')->nullable();
            $table->bigInteger('amount'); // en centimes
            $table->date('estimated_on');
            $table->timestamp('paid_at')->nullable();
            $table->decimal('iva_rate', 5, 2)->nullable();
            $table->decimal('irpf_rate', 5, 2)->nullable();
            $table->boolean('has_iva')->default(true);
            $table->boolean('has_irpf')->default(false);
            $table->string('source')->default('manual'); // manual | recurring | imported

            $table->timestamps();

            $table->index(['user_id', 'estimated_on']);
            $table->index(['user_id', 'paid_at']);
            $table->index('cash_flow_row_id');
        });

        DB::transaction(function () {
            $this->migrateCashFlowCells();
            $this->migrateManualTransactions();
            $this->migrateExpectedIncomes();
            $this->migrateQuarterlyTaxes();
        });

        // Drop dans l'ordre inverse des dépendances (expected_incomes a
        // une FK vers transactions, donc à drop en premier).
        Schema::dropIfExists('expected_incomes');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('cash_flow_cells');
        Schema::dropIfExists('recurring_charges');
        Schema::dropIfExists('quarterly_taxes');
    }

    public function down(): void
    {
        Schema::dropIfExists('movements');
        // Les anciennes tables ne sont pas restaurées — migration one-way.
    }

    /**
     * Chaque cellule non nulle d'un cash_flow_plan devient une Movement
     * rattachée à sa row. estimated_on = 15 du mois.
     */
    private function migrateCashFlowCells(): void
    {
        $now = now();
        $rows = DB::table('cash_flow_cells as c')
            ->join('cash_flow_rows as r', 'r.id', '=', 'c.row_id')
            ->join('cash_flow_plans as p', 'p.id', '=', 'r.plan_id')
            ->select([
                'c.id as cell_id', 'c.row_id', 'c.month', 'c.amount', 'c.paid_at as cell_paid_at',
                'r.kind', 'r.label', 'r.client_name', 'r.category_id', 'r.has_iva', 'r.has_irpf',
                'p.id as plan_id', 'p.user_id', 'p.year',
            ])
            ->where('c.amount', '>', 0)
            ->get();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'user_id' => $row->user_id,
                'cash_flow_plan_id' => $row->plan_id,
                'cash_flow_row_id' => $row->row_id,
                'category_id' => $row->category_id,
                'kind' => $row->kind,
                'label' => $row->label,
                'client_name' => $row->client_name,
                'amount' => $row->amount,
                'estimated_on' => Carbon::create((int) $row->year, (int) $row->month, 15)->toDateString(),
                'paid_at' => $row->cell_paid_at,
                'iva_rate' => null,
                'irpf_rate' => null,
                'has_iva' => $row->has_iva,
                'has_irpf' => $row->has_irpf,
                'source' => 'recurring',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->bulkInsert('movements', $inserts);
    }

    /**
     * Les transactions sans cash_flow_plan_id sont des mouvements manuels
     * (les autres sont déjà couvertes par les cells migrées plus haut).
     */
    private function migrateManualTransactions(): void
    {
        $now = now();
        $rows = DB::table('transactions')
            ->whereNull('cash_flow_plan_id')
            ->get();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'user_id' => $row->user_id,
                'cash_flow_plan_id' => null,
                'cash_flow_row_id' => null,
                'category_id' => $row->category_id,
                'kind' => $row->type, // income | expense
                'label' => $row->label,
                'client_name' => null,
                'amount' => $row->amount,
                'estimated_on' => $row->occurred_on,
                'paid_at' => $row->paid_at,
                'iva_rate' => $row->iva_rate,
                'irpf_rate' => $row->irpf_rate,
                'has_iva' => $row->iva_rate !== null && (float) $row->iva_rate > 0,
                'has_irpf' => $row->irpf_rate !== null && (float) $row->irpf_rate > 0,
                'source' => $row->source ?? 'manual',
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ];
        }

        $this->bulkInsert('movements', $inserts);
    }

    /**
     * Les expected_incomes pending deviennent des Movements income non payées.
     */
    private function migrateExpectedIncomes(): void
    {
        $now = now();
        $rows = DB::table('expected_incomes')->get();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'user_id' => $row->user_id,
                'cash_flow_plan_id' => null,
                'cash_flow_row_id' => null,
                'category_id' => null,
                'kind' => 'income',
                'label' => $row->label,
                'client_name' => $row->client_name,
                'amount' => $row->amount,
                'estimated_on' => $row->expected_on,
                'paid_at' => $row->status === 'received' ? ($row->updated_at ?? $now) : null,
                'iva_rate' => null,
                'irpf_rate' => null,
                'has_iva' => true,
                'has_irpf' => true,
                'source' => 'manual',
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ];
        }

        $this->bulkInsert('movements', $inserts);
    }

    /**
     * Les quarterly_taxes effectivement payées deviennent des Movements
     * kind=tax. L'estimated_on est l'échéance du Modelo (20 du mois
     * suivant la fin de trimestre).
     */
    private function migrateQuarterlyTaxes(): void
    {
        $now = now();
        $rows = DB::table('quarterly_taxes as q')
            ->join('cash_flow_plans as p', 'p.id', '=', 'q.plan_id')
            ->whereNotNull('q.paid_at')
            ->select(['q.*', 'p.user_id', 'p.year'])
            ->get();

        $inserts = [];
        foreach ($rows as $row) {
            $estimated = $this->modeloDeadline((int) $row->year, (int) $row->quarter);
            $kindLabel = strtoupper($row->kind);
            $inserts[] = [
                'user_id' => $row->user_id,
                'cash_flow_plan_id' => $row->plan_id,
                'cash_flow_row_id' => null,
                'category_id' => null,
                'kind' => 'tax',
                'label' => "{$kindLabel} Q{$row->quarter} {$row->year}",
                'client_name' => null,
                'amount' => $row->amount,
                'estimated_on' => $estimated->toDateString(),
                'paid_at' => $row->paid_at,
                'iva_rate' => null,
                'irpf_rate' => null,
                'has_iva' => false,
                'has_irpf' => false,
                'source' => 'manual',
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ];
        }

        $this->bulkInsert('movements', $inserts);
    }

    /**
     * Échéance du Modelo 303/130 d'un trimestre donné.
     * Q1 → 20 avril, Q2 → 20 juillet, Q3 → 20 octobre, Q4 → 30 janvier N+1.
     */
    private function modeloDeadline(int $year, int $quarter): Carbon
    {
        return match ($quarter) {
            1 => Carbon::create($year, 4, 20),
            2 => Carbon::create($year, 7, 20),
            3 => Carbon::create($year, 10, 20),
            4 => Carbon::create($year + 1, 1, 30),
            default => Carbon::create($year, 12, 31),
        };
    }

    /**
     * Insert par batchs de 500 pour éviter de saturer les paramètres SQL.
     */
    private function bulkInsert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            if (! empty($chunk)) {
                DB::table($table)->insert($chunk);
            }
        }
    }
};
