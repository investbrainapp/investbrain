<?php

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HoldingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\TransactionController;
use App\Interfaces\MarketData\NasdaqMarketData;
use Laravel\Jetstream\Http\Controllers\Livewire\PrivacyPolicyController;
use Laravel\Jetstream\Http\Controllers\Livewire\TermsOfServiceController;

Route::get('/', function () {
    if (config('investbrain.self_hosted', true)) {
        
        return redirect(route('dashboard'));
    }

    return view('welcome');
});

Route::get('/test', function () {
    //

    $cookieJar = new CookieJar();

    try {
        $response= Http::withQueryParameters([
            'assetclass' => 'stocks',
        ])
        ->withCookies(['akaalb_ALB_Default' => '=~op=ao_api__east1:ao_api_east1|~rv=76~m=ao_api_east1:0|~os=ff51b6e767de05e2054c5c99e232919a~id=a7cdebc3132b5b30c8507ad37aec9418'], 'api.nasdaq.com')
        ->withHeader('accept', '*/*')
        ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36')
        ->timeout(4)
        ->withOptions(['debug' => true])
        ->get("https://api.nasdaq.com/api/quote/GOOG/info");
    } catch (\Exception $e) {

    }

    return $cookieJar->toArray();
                    return $response->getHeaders();
    return $response;
    // return Http::get("https://api.nasdaq.com/api/quote/GOOG/info?assetclass=stocks");
    
    return (new NasdaqMarketData)->nasdaqClient('AAPL', 'info');
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
