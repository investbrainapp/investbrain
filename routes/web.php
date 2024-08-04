<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    Volt::route('/dashboard', 'dashboard')->name('dashboard');

    Volt::route('/portfolio/create', 'portfolio.create')->name('portfolio.create');
    Volt::route('/portfolio/{portfolio}', 'portfolio.show')->name('portfolio.show');

});
