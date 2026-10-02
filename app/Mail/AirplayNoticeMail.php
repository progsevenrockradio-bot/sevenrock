<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AirplaySchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AirplayNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AirplaySchedule $schedule,
        public string $tipoDestinatario // productora | artista
    ) {}

    public function envelope(): Envelope
    {
        $tipo = $this->schedule->es_primer_pase ? 'Primer pase / Estreno' : 'Programación semanal';
        $subject = sprintf(
            '[%s] Emisión confirmada en Seven Rock Radio: %s - %s',
            $tipo,
            $this->schedule->artista,
            $this->schedule->titulo
        );

        return new Envelope(
            subject: $subject
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.airplay_notice',
            with: [
                'schedule'         => $this->schedule,
                'tipoDestinatario' => $this->tipoDestinatario,
                'diaNombre'        => $this->schedule->dia_nombre,
                'hora'             => $this->schedule->hora_formateada,
                'semana'           => $this->schedule->semana,
            ]
        );
    }
}
