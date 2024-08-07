<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortfolioController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::view('/import-export', 'import-export')->name('import-export');

    Route::get('/portfolio/create', [PortfolioController::class, 'create'])->name('portfolio.create');
    Route::get('/portfolio/{portfolio}', [PortfolioController::class, 'show'])->name('portfolio.show');

});
