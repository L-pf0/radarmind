<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deixa passar só o perfil indicado na rota: ->middleware('perfil:matriz') ou ('perfil:subempresa').
 *
 * Quem tiver outro perfil volta para o próprio dashboard (ou recebe 403 nas rotas JSON).
 */
class GarantirPerfil
{
    public function handle(Request $request, Closure $next, string $perfil): Response
    {
        if ($request->user()?->tipo === $perfil) {
            return $next($request);
        }

        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            abort(403, 'Seu perfil não tem acesso a esta área.');
        }

        return redirect()->route('home');
    }
}
