<?php

use Illuminate\Support\Facades\Route;
use App\Http\ApiControllers\UserController;
use App\Http\ApiControllers\HoldingController;
use App\Http\ApiControllers\PortfolioController;
use App\Http\ApiControllers\MarketDataController;
use App\Http\ApiControllers\TransactionController;

Route::middleware(['auth:sanctum'])->name('api.')->group(function () {

    // user
    Route::get('/me', [UserController::class, 'me'])->name('me');

    // portfolio
    Route::apiResource('/portfolio', PortfolioController::class);

    // transaction
    Route::apiResource('/transaction', TransactionController::class);

    // holding
    Route::get('/holding', [HoldingController::class, 'index'])->name('holding.index');

    // market data
    Route::get('/market-data/{symbol}', [MarketDataController::class, 'show'])->name('market-data.show');
});