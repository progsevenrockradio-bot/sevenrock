<?php

namespace App\Jobs;

use App\Models\PwaPushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Job para enviar notificaciones push "En Vivo" a todos los suscriptores PWA.
 * Se procesa en la cola 'default'.
 */
class SendPwaLiveAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Número máximo de intentos */
    public int $tries = 2;

    /** Timeout en segundos */
    public int $timeout = 120;

    public function __construct(
        protected array $payload = []
    ) {}

    public function handle(): void
    {
        $vapidPublicKey  = env('VAPID_PUBLIC_KEY', '');
        $vapidPrivateKey = env('VAPID_PRIVATE_KEY', '');
        $vapidSubject    = env('VAPID_SUBJECT', 'mailto:prog.sevenrockradio@gmail.com');

        if (empty($vapidPublicKey) || empty($vapidPrivateKey)) {
            Log::warning('[PWA Push Job] VAPID keys no configuradas. Ejecuta: php artisan pwa:vapid-keys');
            return;
        }

        // Inicializar WebPush
        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => $vapidSubject,
                'publicKey'  => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ]);

        $webPush->setReuseVAPIDHeaders(true);

        // Payload JSON para el Service Worker
        $jsonPayload = json_encode([
            'title' => $this->payload['title'] ?? '🔴 Seven Rock Radio — En Vivo',
            'body'  => $this->payload['body']  ?? '¡La señal está al aire!',
            'url'   => $this->payload['url']   ?? '/app/live',
            'icon'  => $this->payload['icon']  ?? '/icons/icon-192.png',
            'badge' => $this->payload['badge'] ?? '/icons/icon-192.png',
            'tag'   => 'live-alert',
        ], JSON_UNESCAPED_UNICODE);

        // Encolar mensajes para todos los suscriptores
        $subscriptions = PwaPushSubscription::all();
        $total = $subscriptions->count();

        if ($total === 0) {
            Log::info('[PWA Push Job] No hay suscriptores activos.');
            return;
        }

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create($sub->toWebPushFormat());
                $webPush->queueNotification($subscription, $jsonPayload);
            } catch (\Throwable $e) {
                Log::warning("[PWA Push Job] Error al encolar para endpoint {$sub->id}: " . $e->getMessage());
            }
        }

        // Enviar todas las notificaciones y procesar respuestas
        $sent   = 0;
        $failed = 0;
        $stale  = [];

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                Log::info('[PWA Push Job] Fallo: ' . $report->getReason());

                // Si la suscripción ya no es válida (410 Gone), eliminarla
                if ($report->isSubscriptionExpired()) {
                    $stale[] = $report->getRequest()->getUri()->__toString();
                }
            }
        }

        // Limpiar suscripciones expiradas
        if (! empty($stale)) {
            PwaPushSubscription::whereIn('endpoint', $stale)->delete();
            Log::info('[PWA Push Job] Eliminadas ' . count($stale) . ' suscripciones expiradas.');
        }

        Log::info("[PWA Push Job] Completado: {$sent} enviadas, {$failed} fallidas de {$total} total.");
    }
}
