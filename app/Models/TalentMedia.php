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
        'backblaze_key',
        'url',
        'title',
        'description',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
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

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }
}
