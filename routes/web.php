<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CashFlowController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialProfileController;
use App\Http\Controllers\OnboardingController;
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

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

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

    Route::get('/asistente', [OnboardingController::class, 'show'])->name('asistente.index');
    Route::post('/asistente', [OnboardingController::class, 'store'])->name('asistente.store');

    Route::get('/flujo-caja', [CashFlowController::class, 'index'])->name('flujo-caja.index');
    Route::put('/flujo-caja/{year}', [CashFlowController::class, 'update'])
        ->whereNumber('year')->name('flujo-caja.update');
});
