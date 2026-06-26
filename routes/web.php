<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
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

    Route::get('/transacciones', function () use ($categories) {
        return Inertia::render('Transacciones/Index', [
            'categories' => $categories,
            'transactions' => [
                // Realizado (pasado)
                ['id' => 1, 'date' => '2026-06-22', 'description' => 'Factura Acme S.L.', 'category' => 'Servicios profesionales', 'amount' => 3200.00, 'iva' => 21, 'irpf' => 15, 'type' => 'income', 'status' => 'realizado'],
                ['id' => 2, 'date' => '2026-06-20', 'description' => 'Alquiler oficina junio', 'category' => 'Alquiler oficina', 'amount' => 650.00, 'iva' => 21, 'irpf' => 0, 'type' => 'expense', 'status' => 'realizado'],
                ['id' => 3, 'date' => '2026-06-19', 'description' => 'Adobe Creative Cloud', 'category' => 'Software / suscripciones', 'amount' => 60.49, 'iva' => 21, 'irpf' => 0, 'type' => 'expense', 'status' => 'realizado'],
                ['id' => 4, 'date' => '2026-06-15', 'description' => 'Cuota autónomos junio', 'category' => 'Cuota autónomos', 'amount' => 469.00, 'iva' => 0, 'irpf' => 0, 'type' => 'expense', 'status' => 'realizado'],
                ['id' => 5, 'date' => '2026-06-12', 'description' => 'Factura Globex Corp.', 'category' => 'Servicios profesionales', 'amount' => 1800.00, 'iva' => 21, 'irpf' => 15, 'type' => 'income', 'status' => 'realizado'],
                ['id' => 6, 'date' => '2026-06-10', 'description' => 'Factura luz oficina', 'category' => 'Suministros', 'amount' => 84.30, 'iva' => 21, 'irpf' => 0, 'type' => 'expense', 'status' => 'realizado'],
                ['id' => 7, 'date' => '2026-06-08', 'description' => 'Fibra + móvil', 'category' => 'Telefonía e internet', 'amount' => 55.00, 'iva' => 21, 'irpf' => 0, 'type' => 'expense', 'status' => 'realizado'],
                ['id' => 8, 'date' => '2026-06-05', 'description' => 'Curso online Vue', 'category' => 'Formación', 'amount' => 199.00, 'iva' => 21, 'irpf' => 0, 'type' => 'expense', 'status' => 'realizado'],
                ['id' => 9, 'date' => '2026-06-03', 'description' => 'Comida cliente Acme', 'category' => 'Comidas y viajes', 'amount' => 42.50, 'iva' => 10, 'irpf' => 0, 'type' => 'expense', 'status' => 'realizado'],
                ['id' => 10, 'date' => '2026-06-01', 'description' => 'Factura InitTech', 'category' => 'Servicios profesionales', 'amount' => 950.00, 'iva' => 21, 'irpf' => 15, 'type' => 'income', 'status' => 'realizado'],
                // Previsto (futuro)
                ['id' => 11, 'date' => '2026-07-05', 'description' => 'Factura Acme S.L. — Hito 2', 'category' => 'Servicios profesionales', 'amount' => 3200.00, 'iva' => 21, 'irpf' => 15, 'type' => 'income', 'status' => 'previsto'],
                ['id' => 12, 'date' => '2026-07-15', 'description' => 'Factura Globex Corp.', 'category' => 'Servicios profesionales', 'amount' => 1800.00, 'iva' => 21, 'irpf' => 15, 'type' => 'income', 'status' => 'previsto'],
                ['id' => 13, 'date' => '2026-07-20', 'description' => 'Factura InitTech', 'category' => 'Servicios profesionales', 'amount' => 950.00, 'iva' => 21, 'irpf' => 15, 'type' => 'income', 'status' => 'previsto'],
                ['id' => 14, 'date' => '2026-08-01', 'description' => 'Factura Stark Industries — Pendiente firma', 'category' => 'Servicios profesionales', 'amount' => 4200.00, 'iva' => 21, 'irpf' => 15, 'type' => 'income', 'status' => 'previsto'],
                ['id' => 15, 'date' => '2026-08-10', 'description' => 'Factura Berlin GmbH (UE intracomunitaria)', 'category' => 'Servicios profesionales', 'amount' => 2500.00, 'iva' => 0, 'irpf' => 0, 'type' => 'income', 'status' => 'previsto'],
            ],
        ]);
    })->name('transacciones.index');

    Route::get('/cargos-recurrentes', function () use ($categories) {
        return Inertia::render('CargosRecurrentes/Index', [
            'categories' => $categories,
            'charges' => [
                ['id' => 1, 'name' => 'Alquiler oficina', 'amount' => 650.00, 'frequency' => 'monthly', 'category' => 'Alquiler oficina', 'dayOfMonth' => 1, 'active' => true],
                ['id' => 2, 'name' => 'Cuota autónomos', 'amount' => 469.00, 'frequency' => 'monthly', 'category' => 'Cuota autónomos', 'dayOfMonth' => 30, 'active' => true],
                ['id' => 3, 'name' => 'Adobe Creative Cloud', 'amount' => 60.49, 'frequency' => 'monthly', 'category' => 'Software / suscripciones', 'dayOfMonth' => 15, 'active' => true],
                ['id' => 4, 'name' => 'Fibra + móvil', 'amount' => 55.00, 'frequency' => 'monthly', 'category' => 'Telefonía e internet', 'dayOfMonth' => 8, 'active' => true],
                ['id' => 5, 'name' => 'Seguro responsabilidad civil', 'amount' => 285.00, 'frequency' => 'yearly', 'category' => 'Servicios profesionales', 'dayOfMonth' => 1, 'active' => true],
                ['id' => 6, 'name' => 'Gestoría', 'amount' => 165.00, 'frequency' => 'quarterly', 'category' => 'Honorarios profesionales', 'dayOfMonth' => 5, 'active' => true],
                ['id' => 7, 'name' => 'GitHub Team', 'amount' => 21.00, 'frequency' => 'monthly', 'category' => 'Software / suscripciones', 'dayOfMonth' => 20, 'active' => false],
            ],
        ]);
    })->name('cargos-recurrentes.index');

    Route::get('/perfil-fiscal', function () {
        return Inertia::render('PerfilFiscal/Edit', [
            'profile' => [
                'fullName' => 'Nicolas del Castillo',
                'nif' => 'Y1234567X',
                'activity' => 'Desarrollo de software',
                'province' => 'Barcelona',
                'regime' => 'direct_simplified',
                'ivaDefault' => 21,
                'irpfDefault' => 15,
                'surchargeEquivalence' => false,
                'intraCommunity' => true,
            ],
        ]);
    })->name('perfil-fiscal.edit');

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
