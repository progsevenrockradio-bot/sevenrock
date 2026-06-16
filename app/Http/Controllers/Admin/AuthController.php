<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.login');
    }

    public function login(Request $request, AuditTrailService $auditTrailService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $auditTrailService->recordSystem('admin.login.failed', 'Intento de acceso fallido', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
                'remember' => $request->boolean('remember'),
            ], 'warning');

            return back()->withErrors([
                'email' => 'Invalid credentials.',
            ])->onlyInput('email');
        }

        if (! Auth::user()?->hasAdminAccess()) {
            // Un usuario autenticado sin privilegios de admin no debe conservar la sesión.
            $auditTrailService->recordSystem('admin.login.denied', 'Usuario autenticado sin acceso de admin', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ], 'warning');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Invalid credentials.',
            ])->onlyInput('email');
        }

        // Regenera el ID de sesión inmediatamente después de un login válido para evitar session fixation.
        $request->session()->regenerate();

        $auditTrailService->recordSystem('admin.login.success', 'Acceso al panel admin', [
            'email' => Auth::user()?->email,
            'name' => Auth::user()?->name,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request, AuditTrailService $auditTrailService): RedirectResponse
    {
        $auditTrailService->recordSystem('admin.logout', 'Salida del panel admin', [
            'email' => Auth::user()?->email,
            'name' => Auth::user()?->name,
            'ip' => $request->ip(),
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function showConfirmForm(): View
    {
        return view('admin.confirm-password');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! \Illuminate\Support\Facades\Hash::check($request->password, $request->user()->password)) {
            return back()->withErrors([
                'password' => 'La contraseña es incorrecta.',
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('admin.dashboard'));
    }

    // ─── Password Reset ───────────────────────────────────────────────────────

    public function showLinkRequestForm(): View
    {
        return view('admin.forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Si ese email existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.');
        }

        // No revelar si el email existe o no (previene enumeración de usuarios)
        return back()->with('status', 'Si ese email existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('admin.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('admin.login')->with('status', 'Contraseña restablecida correctamente. Inicia sesión.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
