<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortfolioController;
use App\Livewire\ShowPortfolio;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Volt::route('/portfolio/create', 'portfolio.create')->name('portfolio.create');
    Volt::route('/portfolio/{portfolio}', 'portfolio.show')->name('portfolio.show');
    

    // Route::get('portfolio/{portfolio}', ShowPortfolio::class)->name('portfolio.show');
    // Route::get('portfolio', ShowPortfolio::class)->name('portfolio.create');
    // Route::resource('portfolio', PortfolioController::class)->only(['show', 'create']);

});
