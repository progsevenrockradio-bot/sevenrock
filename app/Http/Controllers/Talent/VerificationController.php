<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class VerificationController extends Controller
{
    /**
     * Muestra la pantalla de aviso de verificación pendiente al talento.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        /** @var Talent|null $talent */
        $talent = Auth::guard('talent')->user();

        if ($talent && $talent->hasVerifiedEmail()) {
            return redirect()->route('talents.dashboard');
        }

        return view('talentos.auth.verify-email', [
            'talent' => $talent,
        ]);
    }

    /**
     * Valida el enlace temporal firmado y marca el correo del talento como verificado.
     */
    public function verify(Request $request, string|int $id, string $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de verificación ha expirado o no es válido.');
        }

        /** @var Talent $talent */
        $talent = Talent::query()->findOrFail((int) $id);

        if (! hash_equals(sha1((string) $talent->getEmailForVerification()), (string) $hash)) {
            abort(403, 'El código de verificación no coincide con el correo registrado.');
        }

        if (! $talent->hasVerifiedEmail()) {
            $talent->markEmailAsVerified();

            Log::info('Talent email verified successfully', [
                'talent_id' => $talent->id,
                'email' => $talent->email,
                'verified_at' => $talent->email_verified_at?->toIso8601String(),
            ]);

            try {
                app(\App\Services\AuditTrailService::class)->recordModel(
                    'talent.email_verified',
                    $talent,
                    ['email_verified_at' => null],
                    ['email_verified_at' => $talent->email_verified_at],
                    [],
                    ['action' => 'verify_email', 'talent_id' => $talent->id],
                    'info'
                );
            } catch (\Throwable) {
                // Auditoría no crítica
            }
        }

        if (Auth::guard('talent')->check()) {
            return redirect()->route('talents.dashboard')->with(
                'status',
                '¡Correo electrónico verificado con éxito! La titularidad de tus maquetas y tu cuenta ha sido confirmada.'
            );
        }

        return redirect()->route('talents.login')->with(
            'status',
            '¡Correo electrónico verificado con éxito! Inicia sesión para acceder a tu panel de artista.'
        );
    }

    /**
     * Reenvía la notificación con enlace de verificación temporal firmado (con limitador de tasa).
     */
    public function resend(Request $request): RedirectResponse
    {
        /** @var Talent|null $talent */
        $talent = Auth::guard('talent')->user();

        if (! $talent) {
            return redirect()->route('talents.login');
        }

        if ($talent->hasVerifiedEmail()) {
            return redirect()->route('talents.dashboard')->with('status', 'Tu correo electrónico ya ha sido verificado.');
        }

        try {
            $talent->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::error('Error reenviando verificación de correo a talento', [
                'talent_id' => $talent->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['email' => 'No se pudo enviar el correo de verificación en este momento. Por favor inténtalo más tarde.']);
        }

        return back()->with('status', 'Te hemos enviado un nuevo enlace de verificación a tu correo electrónico.');
    }
}

