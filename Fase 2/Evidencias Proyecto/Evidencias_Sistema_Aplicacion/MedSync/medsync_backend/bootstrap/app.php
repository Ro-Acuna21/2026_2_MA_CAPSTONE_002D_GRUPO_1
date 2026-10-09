<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use App\Http\Middleware\ResolveCenterTenant;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Habilita la autenticación por cookies/sesión de Sanctum para el
        // SPA (React) que llama a /api/* con credentials: 'include'.
        $middleware->statefulApi();
        $middleware->alias(['tenant.center' => ResolveCenterTenant::class]);

        // El tenant requiere un usuario autenticado y debe quedar listo antes
        // de cualquier binding de modelos clínicos en la ruta.
        $middleware->appendToPriorityList(Authenticate::class, ResolveCenterTenant::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveCenterTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
