<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\ApiControllers\Controller as ApiController;
use App\Interfaces\MarketData\FallbackInterface;
use App\Interfaces\MarketData\MarketDataInterface;
use App\Models\User;
use App\Support\OidcProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Socialite\Facades\Socialite;
use NumberFormatter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            MarketDataInterface::class,
            FallbackInterface::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentColor::register([
            'primary' => Color::Stone,
            'gray' => Color::Zinc,
            'info' => Color::Blue,
            'success' => Color::Emerald,
            'warning' => Color::Amber,
            'danger' => Color::Red,
        ]);

        JsonResource::withoutWrapping();

        // escape [ ] \ < > in values put into mail Markdown, so text a user wrote (a portfolio title, a name)
        // can't become a link or image in an email sent from this instance
        Markdown::withSecuredEncoding();

        Gate::define('admin', fn (User $user): bool => (bool) $user->admin);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(ApiController::REQUESTS_PER_MINUTE)->by($request->user()?->id ?: $request->ip()));

        Passport::authorizationView('oauth.authorize');

        Passport::tokensCan([
            'mcp:use' => __('View and manage your portfolios, transactions, and holdings'),
        ]);

        Socialite::extend('oidc', function (): OidcProvider {
            $config = config('services.oidc');

            return Socialite::buildProvider(OidcProvider::class, $config)
                ->setIssuer((string) $config['issuer']);
        });

        Arr::macro('skipEmptyValues', function (array $array) {

            return Arr::mapWithKeys($array, function (mixed $value, mixed $key) {
                $result = [];
                if (! empty($value)) {
                    $result[$key] = $value;
                }

                return $result;
            });
        });

        Number::macro('currencySymbol', function (?string $currency = null, ?string $locale = null) {

            $currency = $currency ?? Number::defaultCurrency();

            $locale = $locale ?? Number::defaultLocale();

            $formatter = new NumberFormatter($locale."@currency=$currency", NumberFormatter::CURRENCY);

            return $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
        });
    }
}
