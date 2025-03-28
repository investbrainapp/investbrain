<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Events\LocaleUpdated;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {

            $locale = auth()->user()->getLocale();

            config(['app.locale' => $locale]);
            app('translator')->setLocale(Str::before($locale, '_'));
            app('events')->dispatch(new LocaleUpdated($locale));

            Number::useLocale($locale);
            Number::useCurrency(auth()->user()->getCurrency());
        }
        
        return $next($request);
    }
}
