<?php

namespace App\Exports;

use App\Enums\CashFlowRowKind;
use App\Enums\MovementKind;
use App\Models\CashFlowPlan;
use App\Services\ProvisioningService;
use App\Services\RentaProvisionService;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Génère un .xlsx du flujo de caja d'une année, miroir de la grille
 * affichée dans /flujo-caja : ingresos, gastos, obligaciones fiscales,
 * salario, balance del mes et balance acumulado.
 */
final class CashFlowExporter
{
    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    private const QUARTER_ACCRUAL = [3, 6, 9, 12]; // mois de cierre du trim (1-based)

    private const QUARTER_PAYMENT = [4, 7, 10, null]; // mois du cash-out (Q4 → enero N+1)

    private const COLOR_INCOME = 'D1FAE5';

    private const COLOR_INCOME_DARK = '047857';

    private const COLOR_EXPENSE = 'FEE2E2';

    private const COLOR_EXPENSE_DARK = '991B1B';

    private const COLOR_FISCAL = 'FEF3C7';

    private const COLOR_FISCAL_DARK = '92400E';

    private const COLOR_SALARY = 'EDE9FE';

    private const COLOR_SALARY_DARK = '5B21B6';

    private const COLOR_BALANCE = 'F3F4F6';

    public function __construct(
        private readonly ProvisioningService $provisioning,
        private readonly RentaProvisionService $rentaService,
    ) {}

    public function build(CashFlowPlan $plan): Spreadsheet
    {
        $plan->loadMissing('rows.movements');
        $user = $plan->user;
        $year = $plan->year;

        $rows = $plan->rows->sortBy('sort_order');
        $incomes = $rows->where('kind', CashFlowRowKind::Income)->values();
        $expenses = $rows->where('kind', CashFlowRowKind::Expense)->values();
        $salaryRow = $rows->firstWhere('kind', CashFlowRowKind::Salary);

        $monthlyIncome = $this->monthlyTotals($incomes);
        $monthlyExpense = $this->monthlyTotals($expenses);
        $monthlySalary = $salaryRow ? $this->monthlyForRow($salaryRow) : array_fill(1, 12, 0);

        [$ivaAccrual, $irpfAccrual, $ivaPayment, $irpfPayment] = $this->quarterlyTaxes($user, $year);
        $rentaMonthlyDelta = $this->rentaMonthlyDelta($user, $year);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Flujo $year");

        // En-tête
        $sheet->setCellValue('A1', 'Concepto');
        foreach (self::MONTHS as $i => $m) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 2).'1', $m);
        }
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '374151']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getColumnDimension('A')->setWidth(36);
        foreach (range('B', 'M') as $col) {
            $sheet->getColumnDimension($col)->setWidth(13);
        }
        $sheet->freezePane('B2');

        $row = 2;

        // === Section INGRESOS ===
        $this->writeSectionHeader($sheet, $row++, 'INGRESOS', self::COLOR_INCOME, self::COLOR_INCOME_DARK);
        foreach ($incomes as $income) {
            $this->writeLineRow($sheet, $row++, $income->label, $this->monthlyForRow($income));
        }
        $this->writeTotalRow($sheet, $row++, 'Total previsto', $monthlyIncome, self::COLOR_INCOME, self::COLOR_INCOME_DARK);

        // === Section GASTOS ===
        $this->writeSectionHeader($sheet, $row++, 'GASTOS', self::COLOR_EXPENSE, self::COLOR_EXPENSE_DARK);
        foreach ($expenses as $expense) {
            $this->writeLineRow($sheet, $row++, $expense->label, $this->monthlyForRow($expense));
        }
        $this->writeTotalRow($sheet, $row++, 'Total previsto', $monthlyExpense, self::COLOR_EXPENSE, self::COLOR_EXPENSE_DARK);

        // === Section OBLIGACIONES FISCALES (accrual = fin de trim) ===
        $this->writeSectionHeader($sheet, $row++, 'OBLIGACIONES FISCALES (devengo trimestral)', self::COLOR_FISCAL, self::COLOR_FISCAL_DARK);
        $this->writeLineRow($sheet, $row++, 'IVA (Modelo 303)', $ivaAccrual);
        $this->writeLineRow($sheet, $row++, 'IRPF (Modelo 130)', $irpfAccrual);
        $rentaMonthlyAccrual = [];
        for ($m = 1; $m <= 12; $m++) {
            $rentaMonthlyAccrual[$m] = $rentaMonthlyDelta * $m;
        }
        $this->writeLineRow($sheet, $row++, 'Renta anual a provisionar (acumulado)', $rentaMonthlyAccrual);
        $fiscalTotalAccrual = [];
        for ($m = 1; $m <= 12; $m++) {
            $fiscalTotalAccrual[$m] = $ivaAccrual[$m] + $irpfAccrual[$m];
        }
        $this->writeTotalRow($sheet, $row++, 'Total fiscal del trimestre', $fiscalTotalAccrual, self::COLOR_FISCAL, self::COLOR_FISCAL_DARK);

        // === Section SALARIO ===
        $this->writeSectionHeader($sheet, $row++, 'PAGO A MÍ MISMO', self::COLOR_SALARY, self::COLOR_SALARY_DARK);
        $this->writeLineRow($sheet, $row++, $salaryRow->label ?? 'Salario', $monthlySalary);

        // === Section BALANCE ===
        $monthlyBalance = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyBalance[$m] = $monthlyIncome[$m]
                - $monthlyExpense[$m]
                - $ivaPayment[$m]
                - $irpfPayment[$m]
                - $monthlySalary[$m]
                - $rentaMonthlyDelta;
        }
        $cumulative = [];
        $acc = $plan->starting_balance / 100;
        for ($m = 1; $m <= 12; $m++) {
            $acc += $monthlyBalance[$m];
            $cumulative[$m] = $acc;
        }

        $this->writeBalanceRow($sheet, $row++, 'Balance del mes (cash neto, Q4 fuera de año)', $monthlyBalance);
        $this->writeBalanceRow($sheet, $row++, 'Balance acumulado', $cumulative, bold: true);

        // Bordures globales
        $lastRow = $row - 1;
        $sheet->getStyle("A1:M{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('E5E7EB');

        return $spreadsheet;
    }

    public function downloadResponse(CashFlowPlan $plan): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = $this->build($plan);
        $filename = "flujo-caja-{$plan->year}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<int, float> [1..12] => total euros HT TTC
     */
    private function monthlyForRow($row): array
    {
        $arr = array_fill(1, 12, 0.0);
        foreach ($row->movements as $m) {
            $arr[$m->estimated_on->month] += $m->amount / 100;
        }

        return $arr;
    }

    /**
     * @param  iterable<\App\Models\CashFlowRow>  $rows
     * @return array<int, float>
     */
    private function monthlyTotals($rows): array
    {
        $arr = array_fill(1, 12, 0.0);
        foreach ($rows as $row) {
            foreach ($this->monthlyForRow($row) as $m => $amount) {
                $arr[$m] += $amount;
            }
        }

        return $arr;
    }

    /**
     * @return array{0:array<int,float>, 1:array<int,float>, 2:array<int,float>, 3:array<int,float>}
     *                                                                                               [iva accrual, irpf accrual, iva payment, irpf payment]
     */
    private function quarterlyTaxes($user, int $year): array
    {
        $profile = $user->financialProfile ?? $user->financialProfile()->make();
        $ivaAccrual = array_fill(1, 12, 0.0);
        $irpfAccrual = array_fill(1, 12, 0.0);
        $ivaPayment = array_fill(1, 12, 0.0);
        $irpfPayment = array_fill(1, 12, 0.0);

        for ($q = 1; $q <= 4; $q++) {
            $from = CarbonImmutable::create($year, ($q - 1) * 3 + 1, 1);
            $to = $from->addMonthsNoOverflow(2)->endOfMonth();
            $incomes = $user->movements()->ofKind(MovementKind::Income)->inWindow($from, $to)->get();
            $expenses = $user->movements()->ofKind(MovementKind::Expense)->inWindow($from, $to)->get();
            $taxes = $this->provisioning->forPeriod($incomes, $expenses, $profile);

            $accrualMonth = self::QUARTER_ACCRUAL[$q - 1];
            $paymentMonth = self::QUARTER_PAYMENT[$q - 1];

            $ivaAccrual[$accrualMonth] = $taxes->iva / 100;
            $irpfAccrual[$accrualMonth] = $taxes->irpf / 100;
            if ($paymentMonth !== null) {
                $ivaPayment[$paymentMonth] = $taxes->iva / 100;
                $irpfPayment[$paymentMonth] = $taxes->irpf / 100;
            }
        }

        return [$ivaAccrual, $irpfAccrual, $ivaPayment, $irpfPayment];
    }

    private function rentaMonthlyDelta($user, int $year): float
    {
        $renta = $this->rentaService->forYear($user, $year);

        return round($renta->restanteRenta / 12 / 100, 2);
    }

    private function writeSectionHeader(Worksheet $sheet, int $row, string $label, string $bg, string $fg): void
    {
        $sheet->setCellValue("A$row", $label);
        $sheet->mergeCells("A$row:M$row");
        $sheet->getStyle("A$row")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $fg], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
        ]);
    }

    /**
     * @param  array<int, float>  $monthly
     */
    private function writeLineRow(Worksheet $sheet, int $row, string $label, array $monthly): void
    {
        $sheet->setCellValue("A$row", $label);
        foreach ($monthly as $m => $amount) {
            $coord = Coordinate::stringFromColumnIndex($m + 1).$row;
            $sheet->setCellValue($coord, $amount);
            $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0.00 "€"');
        }
    }

    /**
     * @param  array<int, float>  $monthly
     */
    private function writeTotalRow(Worksheet $sheet, int $row, string $label, array $monthly, string $bg, string $fg): void
    {
        $this->writeLineRow($sheet, $row, $label, $monthly);
        $sheet->getStyle("A$row:M$row")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $fg]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
        ]);
    }

    /**
     * @param  array<int, float>  $monthly
     */
    private function writeBalanceRow(Worksheet $sheet, int $row, string $label, array $monthly, bool $bold = false): void
    {
        $sheet->setCellValue("A$row", $label);
        foreach ($monthly as $m => $amount) {
            $coord = Coordinate::stringFromColumnIndex($m + 1).$row;
            $sheet->setCellValue($coord, $amount);
            $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0.00 "€";[Red]-#,##0.00 "€"');
            if ($bold) {
                $sheet->getStyle($coord)->getFont()->setBold(true);
            }
        }
        $sheet->getStyle("A$row:M$row")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_BALANCE]],
        ]);
        if ($bold) {
            $sheet->getStyle("A$row")->getFont()->setBold(true);
        }
    }
}
