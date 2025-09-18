<?php

declare(strict_types=1);

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ConnectedAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HoldingController;
use App\Http\Controllers\InvitedOnboardingController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\TermsOfServiceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserProfileController;
use App\Support\Spotlight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

Route::get('/', function () {
    if (! config('investbrain.self_hosted', true) && View::exists('landing-page::index')) {

        return view('landing-page::index');
    }

    return redirect(route('dashboard'));
});

Route::middleware(['auth:sanctum', 'web'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::view('/import-export', 'import-export.show')
        ->name('import-export')
        ->when(! config('investbrain.self_hosted'), function ($route) {
            return $route->middleware('verified');
        });

    Route::get('/portfolio/create', [PortfolioController::class, 'create'])->name('portfolio.create');
    Route::get('/portfolio/{portfolio}', [PortfolioController::class, 'show'])->name('portfolio.show');

    Route::get('/portfolio/{portfolio}/{symbol}', [HoldingController::class, 'show'])->name('holding.show');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transaction.index');

    Route::get('/spotlight', function (Request $request) {
        return app()->make(Spotlight::class)->search($request);
    })->name('spotlight');

    Route::get('/user/profile', [UserProfileController::class, 'show'])->name('profile.show');

    Route::get('/user/api-tokens', [ApiTokenController::class, 'index'])
        ->name('api-tokens.index')
        ->when(! config('investbrain.self_hosted'), function ($route) {
            return $route->middleware('verified');
        });
});

// Invited onboarding
Route::get('invite/{portfolio}/{user}', InvitedOnboardingController::class)->name('invited_onboarding')->scopeBindings();

Route::get('/terms', [TermsOfServiceController::class, 'show'])->name('terms.show');
Route::get('/privacy', [PrivacyPolicyController::class, 'show'])->name('policy.show');

// social login routes
Route::get('auth/verify/{connected_account}', [ConnectedAccountController::class, 'verify'])->name('oauth.verify_connected_account');

Route::get('auth/{provider}', [ConnectedAccountController::class, 'redirectToProvider'])->name('oauth.redirect');
Route::get('auth/{provider}/callback', [ConnectedAccountController::class, 'handleProviderCallback'])->name('oauth.callback');
