<?php

use Illuminate\Support\Arr;
use Scheb\YahooFinanceApi\ApiClient;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HoldingController;
use Scheb\YahooFinanceApi\ApiClientFactory;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\TransactionController;
use Tschucki\Alphavantage\Facades\Alphavantage;
use Laravel\Jetstream\Http\Controllers\Livewire\PrivacyPolicyController;
use Laravel\Jetstream\Http\Controllers\Livewire\TermsOfServiceController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {
    //


    return Alphavantage::fundamentals()->overview('TSLA');

    $quote = Alphavantage::core()->quoteEndpoint('FFRHX');

    $quote = Arr::get($quote, 'Global Quote', []);


    return $quote;

    $client = ApiClientFactory::createApiClient();

    return $client->getQuote("IBM");

    return $client->getHistoricalQuoteData(
        "AAPL",
        ApiClient::INTERVAL_1_DAY,
        new \DateTime("-14 days"),
        new \DateTime("today")
    );

    return $client->getHistoricalDividendData(
        "AAPL",
        new \DateTime("-5 years"),
        new \DateTime("today")
    );
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::view('/import-export', 'import-export')->name('import-export');

    Route::get('/portfolio/create', [PortfolioController::class, 'create'])->name('portfolio.create');
    Route::get('/portfolio/{portfolio}', [PortfolioController::class, 'show'])->name('portfolio.show');

    Route::get('/portfolio/{portfolio}/{symbol}', [HoldingController::class, 'show'])->name('holding.show');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transaction.index');
});

// overwrites jetstream routes
Route::get('/terms', [TermsOfServiceController::class, 'show'])->name('terms.show');
Route::get('/privacy', [PrivacyPolicyController::class, 'show'])->name('policy.show');
