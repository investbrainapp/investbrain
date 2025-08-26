<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (Auth::check()) {

            $locale = auth()->user()->getLocale();

            app()->setLocale(Str::before($locale, '_'));

            Number::useLocale($locale);
            Number::useCurrency(auth()->user()->getCurrency());
        }

        return $next($request);
    }
}
