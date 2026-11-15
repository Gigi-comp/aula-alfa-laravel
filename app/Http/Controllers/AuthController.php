<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Informe o email.',
            'email.email' => 'Informe um email válido.',
            'password.required' => 'Informe a senha.',
        ]);

        if (! Auth::attempt($credentials)) {
            // Mensagem genérica: não revela se o email existe ou se a senha está errada.
            // onlyInput('email') preserva o email digitado e nunca devolve a senha.
            return back()
                ->withErrors(['email' => 'Credenciais inválidas.'])
                ->onlyInput('email');
        }

        // Evita session fixation: um novo identificador de sessão após autenticar.
        $request->session()->regenerate();

        if ($request->user()->isAdmin()) {
            // Respeita o destino interno que o visitante tentou abrir antes do login.
            return redirect()->intended(route('admin.users.index'));
        }

        // Usuário comum sempre vai para a página inicial: um destino administrativo
        // guardado na sessão só resultaria em 403.
        $request->session()->forget('url.intended');

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
