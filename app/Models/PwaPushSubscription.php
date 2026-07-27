<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para las suscripciones Web Push de la PWA.
 *
 * @property int    $id
 * @property string $endpoint
 * @property string $p256dh
 * @property string $auth
 * @property string|null $user_agent
 */
class PwaPushSubscription extends Model
{
    protected $table = 'pwa_push_subscriptions';

    protected $fillable = [
        'endpoint',
        'p256dh',
        'auth',
        'user_agent',
    ];

    protected $hidden = [
        'p256dh',
        'auth',
    ];

    /**
     * Crea o actualiza una suscripción por su endpoint.
     * Si el endpoint ya existe, actualiza las claves (puede regenerarse en el navegador).
     */
    public static function upsertSubscription(array $data): static
    {
        return static::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'p256dh'     => $data['p256dh'],
                'auth'       => $data['auth'],
                'user_agent' => $data['user_agent'] ?? null,
            ]
        );
    }

    /**
     * Retorna la suscripción en el formato que espera minishlink/web-push.
     *
     * @return array{endpoint: string, publicKey: string, authToken: string}
     */
    public function toWebPushFormat(): array
    {
        return [
            'endpoint'  => $this->endpoint,
            'publicKey' => $this->p256dh,
            'authToken' => $this->auth,
        ];
    }
}
