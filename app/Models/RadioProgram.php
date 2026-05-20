<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioProgram extends Model
{
    use Auditable;
    protected $table = 'radio_programs';

    protected $fillable = [
        'numero_episodio',
        'titulo_programa',
        'master_program_id',
        'dia_transmision',
        'hora_inicio',
        'hora_fin',
        'conductor',
        'fecha_emision',
        'biografia_invitado',
        'archivo_mp3',
        'enviado_radioboss',
        'radioboss_status',
        'radioboss_verified_at',
        'radioboss_last_error',
        'radioboss_metadata',
        'sync_archive_org',
        'archive_org_status',
        'archive_org_remote_path',
        'archive_org_uploaded_at',
        'archive_org_verified_at',
        'archive_org_last_error',
        'archive_org_metadata',
        'delivery_status',
        'delivery_verified_at',
        'delivery_last_error',
        'delivery_metadata',
        'informacion_fija_programa',
        'genero_musical',
        'facebook_url',
        'instagram_url',
        'caratula_programa',
        'imagen_episodio',
        'imagen_invitado',
        'ruta_ftp_radioboss',
        'resena',
        'live_title',
        'live_description',
        'live_image_url',
        'live_news_ids',
        'preview_news_ids',
        'comentario_episodio',
        'email_notificacion',
    ];

    protected function casts(): array
    {
        return [
            'numero_episodio' => 'integer',
            'master_program_id' => 'integer',
            'fecha_emision' => 'date',
            'enviado_radioboss' => 'boolean',
            'radioboss_verified_at' => 'datetime',
            'radioboss_metadata' => 'array',
            'sync_archive_org' => 'boolean',
            'archive_org_uploaded_at' => 'datetime',
            'archive_org_verified_at' => 'datetime',
            'archive_org_metadata' => 'array',
            'delivery_verified_at' => 'datetime',
            'delivery_metadata' => 'array',
            'live_news_ids' => 'array',
            'preview_news_ids' => 'array',
        ];
    }

    public function masterProgram(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class);
    }
}
