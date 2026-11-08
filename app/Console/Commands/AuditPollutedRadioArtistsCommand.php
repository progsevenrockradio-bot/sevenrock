<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RadioArtist;
use App\Models\RadioProgram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditPollutedRadioArtistsCommand extends Command
{
    protected $signature = 'radio:audit-polluted {--fix : Attempt safe cleanup or normalization}';
    protected $description = 'Dry-run audit of potentially polluted radio_artists records and unpruned local podcast MP3 files';

    public function handle(): int
    {
        $this->info('=== AUDITORÍA 1: Registros de Bandas / Artistas Contaminados (radio_artists) ===');

        $artists = RadioArtist::query()->get();
        $pollutedArtists = [];

        foreach ($artists as $artist) {
            $name = trim((string) $artist->name);
            $bio = (string) ($artist->editorial_summary ?: $artist->biography ?: '');
            $related = (array) $artist->related_artists;

            $issues = [];

            // Check if name is extremely generic or short
            if (mb_strlen($name) < 2) {
                $issues[] = 'Nombre excesivamente corto';
            }

            // Check if bio explicitly talks about a completely different primary band name
            if ($bio !== '') {
                // If the artist name does not appear at all in the first 200 characters of its bio
                $bioSnippet = mb_strtolower(mb_substr($bio, 0, 300));
                $nameLower = mb_strtolower($name);
                if (! str_contains($bioSnippet, $nameLower) && mb_strlen($name) > 3) {
                    $issues[] = 'La biografía inicial no menciona el nombre del artista';
                }
            }

            if (! empty($issues)) {
                $pollutedArtists[] = [
                    'id' => $artist->id,
                    'name' => $name,
                    'issues' => implode('; ', $issues),
                    'source' => $artist->source ?? 'desconocido',
                ];
            }
        }

        if (empty($pollutedArtists)) {
            $this->info('No se detectaron registros contaminados evidentes en radio_artists.');
        } else {
            $this->warn(sprintf('Se encontraron %d artistas potencialmente contaminados:', count($pollutedArtists)));
            $this->table(['ID', 'Artista', 'Problemas detectados', 'Fuente'], $pollutedArtists);
        }

        $this->newLine();
        $this->info('=== AUDITORÍA 2: Inventario Simulado de Ficheros Locales MP3 (Podcasts) ===');

        $episodes = RadioProgram::query()->get();
        $fileInventory = [];

        foreach ($episodes as $episode) {
            $path = trim((string) $episode->archivo_mp3);
            $disk = trim((string) $episode->archivo_mp3_disk) ?: 'public';

            if ($path === '') {
                continue;
            }

            $existsLocal = Storage::disk('public')->exists($path);
            $existsR2 = Storage::disk('r2')->exists($path);

            $reason = 'Conservar';
            if ($existsLocal && $existsR2) {
                $reason = 'Candidato a poda local (ya verificado y respaldado en R2)';
            } elseif ($existsLocal && $episode->radioboss_status === 'radioboss_verified' && $episode->archive_org_status === 'archive_verified') {
                $reason = 'Candidato a poda local (entregado a RadioBOSS y Archive.org)';
            } elseif ($existsLocal) {
                $reason = 'Conservar local (única copia disponible o entregas pendientes)';
            } elseif (! $existsLocal && $existsR2) {
                $reason = 'Definitivo en R2 (sin archivo local residual)';
            } else {
                $reason = 'Referencia huérfana en base de datos (no existe en disco local ni R2)';
            }

            $fileInventory[] = [
                'id' => $episode->id,
                'programa' => $episode->titulo_programa,
                'path' => $path,
                'local' => $existsLocal ? 'SÍ' : 'NO',
                'r2' => $existsR2 ? 'SÍ' : 'NO',
                'estado' => $reason,
            ];
        }

        if (empty($fileInventory)) {
            $this->info('No hay episodios registrados en radio_programs.');
        } else {
            $this->table(['ID', 'Programa', 'Ruta', 'Local?', 'R2?', 'Diagnóstico / Acción'], array_slice($fileInventory, 0, 30));
            if (count($fileInventory) > 30) {
                $this->line(sprintf('... y %d episodios más.', count($fileInventory) - 30));
            }
        }

        return self::SUCCESS;
    }
}

