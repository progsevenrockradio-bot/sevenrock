<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'token',
        'signer_name',
        'signer_email',
        'country',
        'city',
        'title',
        'band_name',
        'content',
        'status',
        'signed_at',
        'signing_ip',
        'pdf_path',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function getSigningUrl(): string
    {
        return route('contratos.firmar', ['token' => $this->token]);
    }

    public function getFormattedContentAttribute(): string
    {
        $text = $this->content;

        // Reemplazar marcadores por los datos reales del contrato
        $text = str_replace('[Nombre de la Banda/Artista]', $this->band_name ?? '[Nombre de la Banda/Artista]', $text);
        $text = str_replace('[Nombre del Firmante]', $this->signer_name ?? '[Nombre del Firmante]', $text);
        $text = str_replace('[Ciudad]', $this->city ?? '[Ciudad]', $text);
        $text = str_replace('[País]', $this->country ?? '[País]', $text);

        if (preg_match('/<[a-z]/i', $text)) {
            return $text;
        }
        return nl2br(e($text));
    }
}
