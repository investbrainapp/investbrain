<?php

declare(strict_types=1);

use App\Http\ApiControllers\HoldingController;
use App\Http\ApiControllers\MarketDataController;
use App\Http\ApiControllers\PortfolioController;
use App\Http\ApiControllers\TransactionController;
use App\Http\ApiControllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->name('api.')->group(function () {

    // user
    Route::get('/me', [UserController::class, 'me'])->name('me');

    // portfolio
    Route::apiResource('/portfolio', PortfolioController::class);

    // transaction
    Route::apiResource('/transaction', TransactionController::class);

    // holding
    Route::get('/holding', [HoldingController::class, 'index'])->name('holding.index');
    Route::get('/holding/{portfolio}/{symbol}', [HoldingController::class, 'show'])->name('holding.show')->scopeBindings();
    Route::put('/holding/{portfolio}/{symbol}', [HoldingController::class, 'update'])->name('holding.update')->scopeBindings();

    // market data
    Route::get('/market-data/{symbol}', [MarketDataController::class, 'show'])->name('market-data.show');
});
