<?php

use Illuminate\Support\Facades\Route;
use App\Http\ApiControllers\UserController;
use App\Http\ApiControllers\HoldingController;
use App\Http\ApiControllers\PortfolioController;
use App\Http\ApiControllers\MarketDataController;
use App\Http\ApiControllers\TransactionController;

Route::middleware(['auth:sanctum'])->group(function () {

    // user
    Route::get('/me', [UserController::class, 'me']);

    // portfolio
    Route::get('/portfolio', [PortfolioController::class, 'index']);

    // transaction
    Route::get('/transaction', [TransactionController::class, 'index']);

    // holding
    Route::get('/holding', [HoldingController::class, 'index']);

    // market data
    Route::get('/market-data/{symbol}', [MarketDataController::class, 'show']);
});