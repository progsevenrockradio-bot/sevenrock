<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentMedia extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'talent_id',
        'type',
        'filename',
        'disk',
        'backblaze_key',
        'url',
        'title',
        'description',
        'mime_type',
        'size',
        'is_exclusive',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_exclusive' => 'boolean',
        ];
    }

    public function getUrlAttribute(?string $value): ?string
    {
        if (! $this->id) {
            return $value;
        }

        // Los documentos se canalizan siempre por su endpoint de descarga protegida
        if ($this->type === 'document') {
            return route('talents.media.download', ['id' => $this->id]);
        }

        // Cualquier archivo no aprobado, exclusivo o alojado en almacenamiento privado se sirve por la ruta protegida
        if ($this->status !== 'approved' || $this->is_exclusive || $this->disk === 'r2_private') {
            return route('talents.media.stream', ['id' => $this->id]);
        }

        return $value ?: route('talents.media.stream', ['id' => $this->id]);
    }

    public function isImage(): bool
    {
        return $this->type === 'photo';
    }

    public function isAudio(): bool
    {
        return $this->type === 'mp3';
    }

    public function isDocument(): bool
    {
        return $this->type === 'document';
    }

    public function resolvedDisk(): ?string
    {
        if (filled($this->disk)) {
            return (string) $this->disk;
        }

        // Si no está registrado explícitamente, el disco es ambiguo / no demostrado
        return null;
    }

    public function hasDemonstratedDisk(): bool
    {
        return filled($this->disk);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
