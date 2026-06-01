<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
}
