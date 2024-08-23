<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!session()->has('locale')) {
            session()->put('locale', $request->getPreferredLanguage(
                config('app.available_locales')
            ));
        }

        app()->setLocale(session('locale'));

        return $next($request);
    }
}