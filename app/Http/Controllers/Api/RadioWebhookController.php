<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayHistory;
use App\Models\Program;
use App\Models\Song;
use App\Services\RadioPlayerService;
use App\Support\RadioPlayerStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class RadioWebhookController extends Controller
{
    public function __construct(
        private readonly RadioPlayerService $playerService,
        private readonly RadioPlayerStateStore $stateStore,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string'],
            'casttitle' => ['nullable', 'string'],
            'artist' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'album' => ['nullable', 'string'],
            'year' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'comment' => ['nullable', 'string'],
            'genre' => ['nullable', 'string'],
            'len' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'listeners' => ['nullable', 'integer', 'min:0'],
            'artwork' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string'],
            'lyrics' => ['nullable', 'string'],
            'band_info' => ['nullable', 'string'],
            'program_slug' => ['nullable', 'string'],
            'program_id' => ['nullable', 'integer'],
            'audio_url' => ['nullable', 'string'],
            'is_live' => ['nullable', 'boolean'],
            'played_at' => ['nullable', 'date'],
        ]);

        abort_unless(hash_equals((string) config('player.webhook.key'), (string) $validated['key']), 403);

        [$artist, $title] = $this->resolveArtistAndTitle($validated);
        $song = $this->resolveSong($artist, $title, $validated);
        $program = $this->resolveProgram($validated);
        $cover = $this->resolveCover($validated, $song);
        $playedAt = isset($validated['played_at']) ? Carbon::parse($validated['played_at']) : now();
        $durationSeconds = $this->resolveDurationSeconds($validated);

        $payload = [
            'song_id' => $song?->id,
            'program_id' => $program?->id,
            'artist' => $artist ?: config('player.defaults.artist'),
            'title' => $title ?: config('player.defaults.title'),
            'album' => $validated['album'] ?? $song?->album,
            'year' => $validated['year'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'genre' => $validated['genre'] ?? null,
            'cover' => $cover,
            'lyrics' => $validated['lyrics'] ?? $song?->lyrics,
            'band_info' => $validated['band_info'] ?? $song?->band_info,
            'band_members' => $song?->band_members ?? [],
            'social_links' => $song?->social_links ?? [],
            'audio_url' => $validated['audio_url'] ?? $song?->audio_url,
            'is_live' => (bool) ($validated['is_live'] ?? true),
            'duration_seconds' => $durationSeconds ?: (int) ($validated['duration_seconds'] ?? $song?->duration_seconds ?? 0),
            'listeners' => (int) ($validated['listeners'] ?? 0),
            'started_at' => $playedAt->toIso8601String(),
            'played_at' => $playedAt->toIso8601String(),
        ];

        $this->playerService->storeTrack($payload);

        if ($this->hasTable('play_history')) {
            PlayHistory::query()->create([
                'song_id' => $song?->id,
                'program_id' => $program?->id,
                'title' => $payload['title'],
                'artist' => $payload['artist'],
                'cover_image' => $cover,
                'source' => 'webhook',
                'duration_seconds' => $payload['duration_seconds'] ?: null,
                'is_live' => $payload['is_live'],
                'metadata' => $validated,
                'played_at' => $playedAt,
            ]);
        }

        if ($song && $this->hasTable('songs')) {
            $song->increment('play_count');
        }

        return response()->json([
            'success' => true,
            'message' => 'Metadata updated.',
            'track' => $payload,
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveArtistAndTitle(array $validated): array
    {
        $artist = trim((string) ($validated['artist'] ?? ''));
        $title = trim((string) ($validated['title'] ?? ''));
        $castTitle = trim((string) ($validated['casttitle'] ?? ''));

        if ($castTitle !== '') {
            $parts = preg_split('/\s+-\s+/', $castTitle, 2) ?: [];
            if ($artist === '') {
                $artist = trim((string) ($parts[0] ?? ''));
            }
            if ($title === '') {
                $title = trim((string) ($parts[1] ?? ''));
            }
        }

        return [$artist, $title];
    }

    private function resolveSong(string $artist, string $title, array $validated): ?Song
    {
        if (! $this->hasTable('songs')) {
            return null;
        }

        if (($validated['song_id'] ?? null) !== null) {
            $song = Song::query()->find((int) $validated['song_id']);
            if ($song) {
                return $song;
            }
        }

        $query = Song::query();
        if ($title !== '') {
            $query->whereRaw('LOWER(title) = ?', [mb_strtolower($title)]);
        }
        if ($artist !== '') {
            $query->whereRaw('LOWER(artist) = ?', [mb_strtolower($artist)]);
        }

        return $query->first();
    }

    private function resolveDurationSeconds(array $validated): int
    {
        $len = trim((string) ($validated['len'] ?? ''));
        if ($len !== '' && preg_match('/^(?:(\d+):)?([0-5]?\d):([0-5]?\d)$/', $len, $matches)) {
            $hours = (int) ($matches[1] ?? 0);
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];

            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        return 0;
    }

    private function resolveProgram(array $validated): ?Program
    {
        if (! $this->hasTable('programs')) {
            return null;
        }

        if (($validated['program_id'] ?? null) !== null) {
            return Program::query()->find((int) $validated['program_id']);
        }

        if (! empty($validated['program_slug'])) {
            return Program::query()->where('slug', $validated['program_slug'])->first();
        }

        return Program::query()->active()->orderBy('sort_order')->first();
    }

    private function resolveCover(array $validated, ?Song $song): string
    {
        if (! empty($validated['cover_image'])) {
            return (string) $validated['cover_image'];
        }

        if (! empty($validated['artwork'])) {
            $decoded = $this->decodeArtwork((string) $validated['artwork']);
            if ($decoded !== null) {
                Storage::disk('public')->put($this->stateStore->coverPath(), $decoded);
                return Storage::disk('public')->url($this->stateStore->coverPath());
            }
        }

        return $song?->cover_url ?: asset(config('player.defaults.cover'));
    }

    private function decodeArtwork(string $payload): ?string
    {
        $payload = preg_replace('#^data:image/\w+;base64,#i', '', $payload) ?? $payload;
        $binary = base64_decode($payload, true);

        return $binary === false ? null : $binary;
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
