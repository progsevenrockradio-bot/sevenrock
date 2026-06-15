<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackSubmission extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'band_name',
        'song_title',
        'contact_email',
        'social_link',
        'file_path',
        'status',
    ];

    /**
     * Opcional: Mutadores genéricos o lógicas rápidas
     * Devuelve la URL pública del archivo si usas el disco r2
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path) {
            return \Illuminate\Support\Facades\Storage::disk('r2')->url($this->file_path);
        }
        
        return null;
    }
}
