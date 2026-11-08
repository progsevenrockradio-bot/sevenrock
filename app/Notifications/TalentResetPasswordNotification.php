<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TalentResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('talents.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Restablecer contraseña - Portal de Talentos (Seven Rock Radio)')
            ->greeting('Hola, ' . ($notifiable->band_name ?? 'Artista'))
            ->line('Recibiste este correo porque se solicitó un restablecimiento de contraseña para tu cuenta en el Portal de Talentos de Seven Rock Radio.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace para restablecer la contraseña caducará en 60 minutos.')
            ->line('Si no solicitaste este restablecimiento, no es necesario realizar ninguna acción; tu cuenta permanece segura.')
            ->salutation('Un saludo cordial,\nEl equipo de Seven Rock Radio');
    }
}
