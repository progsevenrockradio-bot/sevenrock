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
        public AirplaySchedule ,
        public string  //  productora | artista
    ) {}

    public function envelope(): Envelope
    {
         = ->schedule->es_primer_pase ? 'Primer pase / Estreno' : 'Programación semanal';
           = sprintf(
            '[%s] Emisión confirmada en Seven Rock Radio: %s - %s',
            ,
            ->schedule->artista,
            ->schedule->titulo
        );

        return new Envelope(
            subject: 
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.airplay_notice',
            with: [
                'schedule'         => ->schedule,
                'tipoDestinatario' => ->tipoDestinatario,
                'diaNombre'        => ->schedule->dia_nombre,
                'hora'             => ->schedule->hora_formateada,
                'semana'           => ->schedule->semana,
            ]
        );
    }
}
