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
        if (preg_match('/<[a-z]/i', $this->content)) {
            return $this->content;
        }
        return nl2br(e($this->content));
    }
}
