<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortfolioController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    Volt::route('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/portfolio/create', [PortfolioController::class, 'create'])->name('portfolio.create');
    Route::get('/portfolio/{portfolio}', [PortfolioController::class, 'show'])->name('portfolio.show');

});
