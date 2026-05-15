<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class RadioPlayerStateStore
{
    public function read(): array
    {
        $path = $this->path();

        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function write(array $payload): void
    {
        Storage::disk('local')->put(
            $this->path(),
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    public function forget(): void
    {
        Storage::disk('local')->delete($this->path());
    }

    public function path(): string
    {
        return config('player.state.file', 'radio/nowplaying.json');
    }

    public function coverPath(): string
    {
        return config('player.state.cover_path', 'radio/current-cover.jpg');
    }
}
