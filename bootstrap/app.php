<?php

use App\Http\Middleware\GarantirPerfil;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ->middleware('perfil:matriz') ou ->middleware('perfil:subempresa')
        $middleware->alias([
            'perfil' => GarantirPerfil::class,
        ]);

        // Na hospedagem o HTTPS termina no proxy da plataforma: confia nele para saber
        // o endereço e o protocolo reais (https) de quem acessa
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
