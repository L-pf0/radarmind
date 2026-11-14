<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function mostrarLogin(): View
    {
        return view('auth.login');
    }

    public function autenticar(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Site público: no máximo 5 tentativas erradas por minuto para o mesmo e-mail e IP
        $chave = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($chave, 5)) {
            return back()->withErrors([
                'email' => 'Muitas tentativas de acesso. Tente de novo em '.RateLimiter::availableIn($chave).' segundos.',
            ])->onlyInput('email');
        }

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($chave, 60);

            return back()->withErrors([
                'email' => 'E-mail ou chave de acesso inválidos.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();

        $user = $request->user();
        assert($user instanceof User);

        return redirect()->intended(
            route($user->ehMatriz() ? 'matriz' : 'subempresa')
        );
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
