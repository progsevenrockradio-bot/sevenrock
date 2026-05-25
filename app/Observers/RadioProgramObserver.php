<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ProcessMp3Job;
use App\Models\RadioProgram;
use Illuminate\Support\Facades\Auth;

final class RadioProgramObserver
{
    public function saved(RadioProgram $radioProgram): void
    {
        $shouldProcess = filled($radioProgram->archivo_mp3)
            && (
                $radioProgram->wasRecentlyCreated
                || $radioProgram->wasChanged('archivo_mp3')
            )
            && ! str_starts_with((string) $radioProgram->archivo_mp3, 'programas_procesados/');

        if (! $shouldProcess) {
            return;
        }

        ProcessMp3Job::dispatch(
            $radioProgram,
            (string) $radioProgram->archivo_mp3,
            false,
            Auth::id(),
            Auth::user()?->name,
            Auth::user()?->email,
        );
    }
}
