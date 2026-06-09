<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewRelease extends Model
{
    use Auditable;

    protected $fillable = [
        'title',
        'slug',
        'artist_name',
        'radio_artist_id',
        'released_at',
        'cover_image',
        'audio_path',
        'youtube_url',
        'spotify_url',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function radioArtist(): BelongsTo
    {
        return $this->belongsTo(RadioArtist::class, 'radio_artist_id');
    }

    public function getCoverImageUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->cover_image) ?: asset('assets/lucille/album3.jpg');
    }

    public function getAudioUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->audio_path) ?: '';
    }

    public function getYoutubeEmbedUrlAttribute(): string
    {
        $url = trim((string) $this->youtube_url);
        if ($url === '') {
            return '';
        }

        if (str_contains($url, 'youtube.com/embed/')) {
            return $url;
        }

        if (preg_match('~(?:v=|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1] . '?autoplay=0&enablejsapi=1&wmode=transparent&rel=0&showinfo=0';
        }

        return $url;
    }
}
