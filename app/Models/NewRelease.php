<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NewRelease extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        $bumpVersion = static function (): void {
            \Illuminate\Support\Facades\Cache::forever('cache.version.new_releases', now()->timestamp);
        };

        static::saved($bumpVersion);
        static::deleted($bumpVersion);

        static::saved(static function (NewRelease $newRelease): void {
            $wasActive = filter_var($newRelease->getOriginal('is_active'), FILTER_VALIDATE_BOOLEAN);
            $isActiveNow = filter_var($newRelease->is_active, FILTER_VALIDATE_BOOLEAN);

            $newlyActive = $newRelease->wasRecentlyCreated && $isActiveNow;
            $updatedToActive = ! $wasActive && $isActiveNow;

            if ($newlyActive || $updatedToActive) {
                $newRelease->sendPublishedNotification();

                try {
                    \App\Models\CommunityPost::query()->create([
                        'user_id' => null,
                        'talent_id' => null,
                        'content' => "📢 ¡Nuevo Lanzamiento en la señal de Seven Rock Radio! 🎸\n\nEscucha \"{$newRelease->title}\" de {$newRelease->artist_name}. Descubre la reseña completa, su música y redes sociales ingresando al enlace:\n" . route('new-releases.single', $newRelease->slug),
                        'youtube_url' => $newRelease->youtube_url ?: null,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Fallo al crear post automático en Muro para lanzamiento ID {$newRelease->id}: " . $e->getMessage());
                }
            }
        });
    }

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
        'author_email',
        'notification_sender',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getUrlAttribute(): string
    {
        return route('new-releases.single', $this->slug);
    }

    public function sendPublishedNotification(): void
    {
        if (! Schema::hasColumn($this->getTable(), 'author_email')) {
            return;
        }

        $emailString = trim((string) $this->author_email);
        if ($emailString === '') {
            return;
        }

        // Dividir por punto y coma (;) o coma (,) y limpiar espacios
        $emails = array_values(array_filter(array_map('trim', preg_split('/[;,]/', $emailString))));
        if (empty($emails)) {
            return;
        }

        try {
            $sender = Schema::hasColumn($this->getTable(), 'notification_sender') && $this->notification_sender
                ? trim((string) $this->notification_sender)
                : config('mail.from.address');

            Mail::to($emails)->send(
                new \App\Mail\NewReleasePublishedMail($this, $sender)
            );

            Log::info("Email de notificación de publicación enviado a los autores (" . implode(', ', $emails) . ") para el lanzamiento ID: {$this->id}");
        } catch (\Throwable $e) {
            Log::error("Fallo al enviar email de notificación de publicación para lanzamiento ID {$this->id}: " . $e->getMessage());
        }
    }

    public function radioArtist(): BelongsTo
    {
        return $this->belongsTo(RadioArtist::class, 'radio_artist_id');
    }

    public function getCoverImageUrlAttribute(): string
    {
        if ($this->cover_image) {
            return PublicMediaUrl::normalizePublicUrl($this->cover_image);
        }

        $settings = ThemeSetting::current();
        if ($settings && $settings->email_default_cover_path) {
            return PublicMediaUrl::normalizePublicUrl($settings->email_default_cover_path);
        }

        return asset('assets/lucille/album3.jpg');
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
