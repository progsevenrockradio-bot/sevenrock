<?php
declare(strict_types=1);
namespace App\Mail;
use App\Models\ModerationItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
/**
 * Aviso al remitente cuando se APRUEBA o se DENIEGA su envio desde la moderacion.
 *
 * Se usa para todos los tipos que no tienen correo propio (evento, producto, album, muro,
 * comentario, contacto, afiliado, banda de agencia, contrato, alta de talento…).
 * Las maquetas siguen usando SubmissionStatusUpdated (su plantilla de siempre) y los talentos
 * mantienen ContentApprovedMail cuando se aprueban desde su propio panel.
 */
class ModerationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;
    /** Etiqueta legible del tipo: "Evento", "Producto", "Album"… */
    public string $etiqueta;
    /** true = aprobado, false = denegado */
    public bool $aprobado;
    public function __construct(
        public ModerationItem $item
    ) {
        $this->aprobado = $this->item->status === 'approved';
        $this->etiqueta = \App\Support\ModerationType::tryFrom((string) $this->item->type)?->label() ?? ucfirst((string) $this->item->type);
    }
    public function envelope(): Envelope
    {
        $quePaso = $this->aprobado ? 'aprobado' : 'no seleccionado';
        $titulo = trim((string) $this->item->title);
        return new Envelope(
            subject: 'Tu envío de ' . $this->etiqueta . ' ha sido ' . $quePaso . ($titulo !== '' ? ': ' . $titulo : '') . ' - ' . config('app.name'),
        );
    }
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.moderation.decision',
            with: [
                'etiqueta' => $this->etiqueta,
                'aprobado' => $this->aprobado,
                'titulo' => (string) $this->item->title,
                'nombre' => (string) $this->item->submitter_name,
                'resumen' => (string) $this->item->summary,
            ],
        );
    }
    public function attachments(): array
    {
        return [];
    }
}
