<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Number;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! session()->has('locale')) {
            session()->put('locale', $request->getPreferredLanguage());
        }

        app()->setLocale(session('locale'));
        Number::useCurrency(auth()->user()?->currency ?? config('investbrain.base_currency'));

        return $next($request);
    }
}
