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
}
