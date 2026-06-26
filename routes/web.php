<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FinancialProfileController;
use App\Http\Controllers\RecurringChargeController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'name' => config('app.name', 'Fitnances'),
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Prototype routes — fake data, no business logic
|--------------------------------------------------------------------------
*/

$categories = [
    'Servicios profesionales',
    'Material de oficina',
    'Software / suscripciones',
    'Alquiler oficina',
    'Cuota autónomos',
    'Suministros',
    'Formación',
    'Comidas y viajes',
    'Honorarios profesionales',
    'Telefonía e internet',
];

Route::middleware('auth')->group(function () use ($categories) {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');

    Route::get('/transacciones', [TransactionController::class, 'index'])->name('transacciones.index');
    Route::post('/transacciones', [TransactionController::class, 'store'])->name('transacciones.store');
    Route::put('/transacciones/{transaction}', [TransactionController::class, 'update'])->name('transacciones.update');
    Route::delete('/transacciones/{transaction}', [TransactionController::class, 'destroy'])->name('transacciones.destroy');
    Route::patch('/transacciones/{transaction}/realize', [TransactionController::class, 'realize'])->name('transacciones.realize');

    Route::get('/cargos-recurrentes', [RecurringChargeController::class, 'index'])->name('cargos-recurrentes.index');
    Route::post('/cargos-recurrentes', [RecurringChargeController::class, 'store'])->name('cargos-recurrentes.store');
    Route::put('/cargos-recurrentes/{cargo}', [RecurringChargeController::class, 'update'])->name('cargos-recurrentes.update');
    Route::delete('/cargos-recurrentes/{cargo}', [RecurringChargeController::class, 'destroy'])->name('cargos-recurrentes.destroy');
    Route::patch('/cargos-recurrentes/{cargo}/toggle', [RecurringChargeController::class, 'toggle'])->name('cargos-recurrentes.toggle');

    Route::get('/perfil-fiscal', [FinancialProfileController::class, 'edit'])->name('perfil-fiscal.edit');
    Route::put('/perfil-fiscal', [FinancialProfileController::class, 'update'])->name('perfil-fiscal.update');

    Route::get('/dashboard', function () {
        $today = now();

        return Inertia::render('Dashboard', [
            'headline' => 2840.50,
            'cash' => 9420.10,
            'available' => 6120.40,
            'provisions' => [
                'iva' => 1850.20,
                'irpf' => 980.50,
                'total' => 2830.70,
            ],
            'forecast' => [
                'startingCash' => 9420.10,
                'expectedIncome' => 8400.00,
                'projectedCharges' => 5320.40,
                'projectedBalance' => 12499.70,
                'from' => $today->toDateString(),
                'to' => $today->copy()->addDays(90)->toDateString(),
            ],
            'upcomingDeadlines' => [
                [
                    'modelo' => 'Modelo 303',
                    'label' => 'IVA trimestral',
                    'date' => $today->copy()->addDays(12)->toDateString(),
                    'daysLeft' => 12,
                    'amount' => 1850.20,
                ],
                [
                    'modelo' => 'Modelo 130',
                    'label' => 'Pago fraccionado IRPF',
                    'date' => $today->copy()->addDays(12)->toDateString(),
                    'daysLeft' => 12,
                    'amount' => 980.50,
                ],
                [
                    'modelo' => 'Cuota autónomos',
                    'label' => 'Domiciliación mensual',
                    'date' => $today->copy()->addDays(5)->toDateString(),
                    'daysLeft' => 5,
                    'amount' => 469.00,
                ],
            ],
        ]);
    })->name('dashboard');

    Route::get('/flujo-caja', function () use ($categories) {
        return Inertia::render('FlujoCaja/Index', [
            'year' => 2026,
            'startingBalance' => 4200.00,
            'categories' => $categories,
            'incomes' => [
                ['client' => 'Acme S.L.', 'monthly' => [3200, 0, 3200, 0, 3200, 0, 3200, 0, 3200, 0, 3200, 0]],
                ['client' => 'Globex Corp.', 'monthly' => [0, 1800, 0, 1800, 0, 1800, 0, 1800, 0, 1800, 0, 1800]],
                ['client' => 'InitTech', 'monthly' => [950, 950, 950, 950, 950, 950, 950, 950, 950, 950, 950, 950]],
                ['client' => 'Stark Industries', 'monthly' => [0, 0, 0, 4200, 0, 0, 0, 4200, 0, 0, 0, 4200]],
                ['client' => 'Berlin GmbH (UE)', 'monthly' => [0, 2500, 0, 0, 2500, 0, 0, 2500, 0, 0, 2500, 0]],
            ],
            'expenses' => [
                ['name' => 'Alquiler oficina', 'category' => 'Alquiler', 'monthly' => array_fill(0, 12, 650)],
                ['name' => 'Cuota autónomos', 'category' => 'Cuota', 'monthly' => array_fill(0, 12, 469)],
                ['name' => 'Adobe Creative Cloud', 'category' => 'Software', 'monthly' => array_fill(0, 12, 60.49)],
                ['name' => 'Fibra + móvil', 'category' => 'Telco', 'monthly' => array_fill(0, 12, 55)],
                ['name' => 'Gestoría', 'category' => 'Honorarios', 'monthly' => [165, 0, 0, 165, 0, 0, 165, 0, 0, 165, 0, 0]],
                ['name' => 'Seguro RC', 'category' => 'Seguros', 'monthly' => [285, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]],
            ],
            'salary' => [
                'amount' => 2200,
                'monthly' => array_fill(0, 12, 2200),
            ],
            'quarterlyTaxes' => [
                'iva' => [1850, 1620, 2100, 1980], // payés en Avr, Jul, Oct (Janv N+1 ignoré)
                'irpf' => [980, 720, 1150, 1020],
                'irpfExempt' => false,
            ],
        ]);
    })->name('flujo-caja.index');

    Route::get('/asistente', function () {
        return Inertia::render('Asistente/Index', [
            'initial' => [
                'annualRevenue' => null,
                'cuotaMonthly' => null,
                'monthlySalary' => null,
            ],
        ]);
    })->name('asistente.index');
});
