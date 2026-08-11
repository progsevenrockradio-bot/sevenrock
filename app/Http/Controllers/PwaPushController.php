<?php

namespace App\Http\Controllers;

use App\Jobs\SendPwaLiveAlertJob;
use App\Models\PwaPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PwaPushController
 *
 * Gestiona las suscripciones Web Push de la PWA móvil.
 * Rutas:
 *   POST /app/push/subscribe      → subscribe()
 *   DELETE /app/push/unsubscribe  → unsubscribe()
 *   POST /app/push/live-alert     → sendLiveAlert()  [solo admin/webhook]
 *   GET  /app/push/vapid-key      → vapidPublicKey() [devuelve la clave pública VAPID]
 */
class PwaPushController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Clave pública VAPID (para el frontend)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Devuelve la clave pública VAPID que el frontend necesita
     * para registrar la suscripción push.
     */
    public function vapidPublicKey(): JsonResponse
    {
        $key = config()->get('app.vapid_public_key') ?: env('VAPID_PUBLIC_KEY', '');

        if (empty($key)) {
            return response()->json(['error' => 'VAPID no configurado. Ejecuta: php artisan pwa:vapid-keys'], 503);
        }

        return response()->json(['publicKey' => $key]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Suscribir dispositivo
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Guarda o actualiza la suscripción push del dispositivo.
     *
     * Body esperado (JSON):
     * {
     *   "endpoint": "https://...",
     *   "keys": { "p256dh": "...", "auth": "..." }
     * }
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint'   => ['required', 'string', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth'   => ['required', 'string'],
        ]);

        try {
            PwaPushSubscription::upsertSubscription([
                'endpoint'   => $validated['endpoint'],
                'p256dh'     => $validated['keys']['p256dh'],
                'auth'       => $validated['keys']['auth'],
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['status' => 'subscribed'], 201);
        } catch (\Throwable $e) {
            Log::error('[PWA Push] Error al guardar suscripción: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Desuscribir dispositivo
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Elimina una suscripción por su endpoint.
     *
     * Body esperado (JSON): { "endpoint": "https://..." }
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = $request->validate(['endpoint' => ['required', 'string']])['endpoint'];

        try {
            PwaPushSubscription::where('endpoint', $endpoint)->delete();
            return response()->json(['status' => 'unsubscribed']);
        } catch (\Throwable $e) {
            Log::error('[PWA Push] Error al eliminar suscripción: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Enviar alerta "En Vivo" (llamada desde admin o webhook de RadioBoss)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispara una alerta push a todos los suscriptores.
     * Protegido con una clave webhook para evitar acceso público.
     *
     * Body esperado (JSON, opcional):
     * {
     *   "title": "¡Seven Rock Radio en vivo!",
     *   "body": "Nuevo programa comenzando ahora",
     *   "url": "/app/live"
     * }
     */
    public function sendLiveAlert(Request $request): JsonResponse
    {
        // Verificar clave webhook (misma que usa RadioBoss)
        $webhookKey = config('player.webhook.key', '');
        $requestKey = $request->header('X-Webhook-Key') ?: $request->input('webhook_key', '');

        if (! empty($webhookKey) && ! hash_equals($webhookKey, (string) $requestKey)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $count = PwaPushSubscription::count();
        if ($count === 0) {
            return response()->json(['status' => 'no_subscribers', 'count' => 0]);
        }

        $payload = [
            'title' => $request->input('title', '🔴 Seven Rock Radio — En Vivo'),
            'body'  => $request->input('body',  '¡La señal está al aire! Escúchanos ahora.'),
            'url'   => $request->input('url',   '/app/live'),
            'icon'  => '/icons/icon-192.png',
            'badge' => '/icons/icon-192.png',
        ];

        SendPwaLiveAlertJob::dispatch($payload);

        Log::info("[PWA Push] Alerta live despachada a {$count} suscriptores.");

        return response()->json([
            'status'      => 'queued',
            'subscribers' => $count,
        ]);
    }
}
