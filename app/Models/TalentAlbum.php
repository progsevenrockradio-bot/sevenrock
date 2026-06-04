<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TalentAlbum extends Model
{
    protected $table = 'talent_albums';

    protected $fillable = [
        'talent_id',
        'title',
        'slug',
        'cover_image',
        'cover_url',
        'release_date',
        'description',
        'tracks',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'tracks' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function coverUrl(): ?string
    {
        if ($this->cover_url) {
            return $this->cover_url;
        }

        if ($this->cover_image) {
            try {
                return \Illuminate\Support\Facades\Storage::disk('backblaze')->url($this->cover_image);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $album): void {
            if (empty($album->slug)) {
                $album->slug = Str::slug($album->title);
            }
        });

        $bumpVersion = static function (): void {
            Cache::forever('cache.version.albums', now()->timestamp);
        };

        static::saved($bumpVersion);
        static::deleted($bumpVersion);
        static::restored($bumpVersion);
    }
}
