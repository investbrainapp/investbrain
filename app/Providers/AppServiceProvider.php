<?php

declare(strict_types=1);

namespace App\Providers;

use App\Interfaces\MarketData\FallbackInterface;
use App\Interfaces\MarketData\MarketDataInterface;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
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
