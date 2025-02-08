<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = auth()->user()?->getLocale() ?? config('app.locale');
        $language = Str::before($locale, '_');
        $currency = auth()->user()?->getCurrency() ?? config('investbrain.base_currency');

        config(['app.locale' => $locale]);
        app('translator')->setLocale($language);
        app('events')->dispatch(new LocaleUpdated($locale));
        Number::useLocale($locale);

        Number::useCurrency($currency);

        return $next($request);
    }
}
