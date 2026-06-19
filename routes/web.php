<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'name' => config('app.name', 'Fitnances'),
    ]);
});

Route::get('/dashboard', DashboardController::class)->name('dashboard');
