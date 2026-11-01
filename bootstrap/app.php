<?php

declare(strict_types=1);

use App\Http\Middleware\LocalizationMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('web', LocalizationMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // report disallowed API filters and sorts as validation errors, matching other query parameters
        $exceptions->map(fn (InvalidFilterQuery $e) => ValidationException::withMessages([
            'filter' => 'The filter field may only contain: '.$e->allowedFilters->implode(', ').'.',
        ]));
        $exceptions->map(fn (InvalidSortQuery $e) => ValidationException::withMessages([
            'sort' => 'The sort field may only contain: '.$e->allowedSorts->implode(', ').'.',
        ]));
    })->create();
