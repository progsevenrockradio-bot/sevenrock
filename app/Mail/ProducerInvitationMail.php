<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MasterProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProducerInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly MasterProgram $program,
        public readonly string $invitationUrl
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Actualización de Perfil - ' . $this->program->name,
        );
    }

    public function content(): Content
    {
        $bodyHtml = '<p>Hola,</p>';
        $bodyHtml .= '<p>Para mantener al día el perfil de tu programa en Seven Rock Radio, hemos generado un enlace temporal seguro para que puedas actualizar tu información (Redes sociales, descripción, horario, imagen, etc).</p>';
        $bodyHtml .= '<p style="margin-top:20px;margin-bottom:20px;"><a href="' . $this->invitationUrl . '" style="display:inline-block;padding:12px 24px;background:#a855f7;color:#fff;text-decoration:none;font-weight:bold;border-radius:4px;">Actualizar Programa</a></p>';
        $bodyHtml .= '<p>Este enlace es único y tiene una vigencia limitada.</p>';
        $bodyHtml .= '<p>Saludos,<br>Equipo de Seven Rock Radio</p>';

        return new Content(
            markdown: 'emails.producer-invitation',
            with: [
                'program' => $this->program,
                'subjectLine' => 'Actualización de Perfil - ' . $this->program->name,
                'bodyHtml' => $bodyHtml,
            ],
        );
    }
}
