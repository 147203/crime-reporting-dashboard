<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAccountUsable;
use App\Http\Middleware\EnsurePreflightPasses;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsurePreflightPasses::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EnsureAccountUsable::class]);
        $middleware->alias(['role' => EnsureRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
