<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RadioProgram;
use Illuminate\Support\Facades\DB;

final class RadioProgramObserver
{
    public function saved(RadioProgram $radioProgram): void
    {
        // El pipeline de audio ya no se dispara desde el observer.
        // La responsabilidad quedó centralizada en el controlador admin
        // y en los flujos manuales de Filament para evitar duplicados.
    }

    /**
     * Cuando se elimina un programa de radio, también se eliminan
     * los trabajos pendientes y fallidos asociados a él.
     */
    public function deleted(RadioProgram $radioProgram): void
    {
        $programId = $radioProgram->id;

        // Eliminar jobs pendientes que referencien este programa
        DB::table('jobs')
            ->where('payload', 'like', '%radioProgramId%;i:' . $programId . ';%')
            ->delete();

        // Eliminar failed jobs que referencien este programa
        DB::table('failed_jobs')
            ->where('payload', 'like', '%radioProgramId%;i:' . $programId . ';%')
            ->delete();
    }
}
