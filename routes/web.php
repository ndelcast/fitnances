<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
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

    Route::get('/email/verify', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::patch('/transactions/{transaction}/realize', [TransactionController::class, 'realize'])->name('transactions.realize');

    Route::get('/recurring-charges', [RecurringChargeController::class, 'index'])->name('recurring-charges.index');
    Route::post('/recurring-charges', [RecurringChargeController::class, 'store'])->name('recurring-charges.store');
    Route::put('/recurring-charges/{charge}', [RecurringChargeController::class, 'update'])->name('recurring-charges.update');
    Route::delete('/recurring-charges/{charge}', [RecurringChargeController::class, 'destroy'])->name('recurring-charges.destroy');
    Route::patch('/recurring-charges/{charge}/toggle', [RecurringChargeController::class, 'toggle'])->name('recurring-charges.toggle');

    Route::get('/fiscal-profile', [FinancialProfileController::class, 'edit'])->name('fiscal-profile.edit');
    Route::put('/fiscal-profile', [FinancialProfileController::class, 'update'])->name('fiscal-profile.update');

    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::get('/cash-flow', [CashFlowController::class, 'index'])->name('cash-flow.index');
    Route::put('/cash-flow/{year}', [CashFlowController::class, 'update'])
        ->whereNumber('year')->name('cash-flow.update');
});
