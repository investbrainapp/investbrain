<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortfolioController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Volt::route('portfolio/{portfolio}', 'portfolio.show')->name('portfolio.show');

    Route::get('portfolio/create', [PortfolioController::class, 'create'])->name('portfolio.create');
    Route::get('portfolio/{portfolio}', [PortfolioController::class, 'show'])->name('portfolio.show');



});
