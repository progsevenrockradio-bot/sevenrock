<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TalentVerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'talents.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage)
            ->subject('Verifica tu correo electrónico - Portal de Talentos (Seven Rock Radio)')
            ->greeting('Hola, ' . ($notifiable->band_name ?? 'Artista'))
            ->line('Gracias por registrar tu banda en el Portal de Talentos y el Muro del Rock de Seven Rock Radio.')
            ->line('Por favor, haz clic en el siguiente botón para verificar tu dirección de correo electrónico y acreditar la propiedad de tus maquetas y material:')
            ->action('Verificar mi correo electrónico', $verificationUrl)
            ->line('Este enlace de verificación es seguro y caducará en 60 minutos.')
            ->line('Si tú no creaste esta cuenta en Seven Rock Radio, puedes ignorar este mensaje con total tranquilidad.')
            ->salutation("Un saludo cordial,\nEl equipo de Seven Rock Radio");
    }
}

