<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateAuthController extends Controller
{
    public function showRegisterForm(): View
    {
        return view('afiliados.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $isPending = app(\App\Services\ModerationService::class)->needsModeration('affiliate');

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => !$isPending,
        ]);

        app(\App\Services\ModerationService::class)->registerIfRequired('affiliate', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'title' => "Registro de Fan: {$user->name}",
            'summary' => "Usuario: {$user->email}",
            'submitter_name' => $user->name,
            'submitter_email' => $user->email,
        ]);

        if (!$isPending) {
            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();
            return redirect()->route('comunidad.muro')->with('status', '¡Bienvenido al Fan Club de Seven Rock Radio!');
        }

        return redirect()->route('comunidad.muro')->with('status', 'Tu cuenta ha sido creada y está pendiente de aprobación.');
    }

    public function showLoginForm(): View
    {
        return view('afiliados.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Credenciales inválidas.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('comunidad.muro'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('afiliados.login');
    }
}
