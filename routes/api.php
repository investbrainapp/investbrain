<?php

use App\Http\ApiControllers\PortfolioController;
use Illuminate\Support\Facades\Route;
use App\Http\ApiControllers\UserController;

Route::middleware(['auth:sanctum'])->group(function () {

    // user
    Route::get('/me', [UserController::class, 'me']);

    // portfolio
    Route::get('/portfolio', [PortfolioController::class, 'index']);

    // transaction

    // holding
});