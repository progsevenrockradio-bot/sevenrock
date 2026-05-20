<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterProgramsSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/programas.json');
        if (! is_file($jsonPath)) {
            return;
        }

        $programas = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($programas) || $programas === []) {
            return;
        }

        $diasMap = [
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO',
        ];

        DB::table('master_programs')->truncate();

        foreach ($programas as $index => $program) {
            if (! is_array($program)) {
                continue;
            }

            $diaCodigo = (int) ($program['diaCodigo'] ?? 1);
            $stats = is_array($program['stats'] ?? null) ? $program['stats'] : [];
            $createdAt = ! empty($stats['updated_at']) ? date('Y-m-d H:i:s', strtotime((string) $stats['updated_at'])) : now();

            DB::table('master_programs')->insert([
                'id' => $index + 1,
                'nombre' => trim((string) ($program['nombre'] ?? '')),
                'conductor' => trim((string) ($program['artista'] ?? '')),
                'dia_transmision' => $diasMap[$diaCodigo] ?? 'LUNES',
                'hora_transmision' => $this->normalizeTime($program['horaTransmision'] ?? null),
                'timezone' => 'America/Caracas',
                'duracion_minutos' => 120,
                'genero' => trim((string) ($program['genero'] ?? 'Rock')),
                'caratula_url' => trim((string) ($program['caratulaUrl'] ?? '')),
                'descripcion' => trim((string) ($program['descripcion'] ?? '')),
                'live_title' => trim((string) ($program['nombre'] ?? '')),
                'live_description' => trim((string) ($program['descripcion'] ?? '')),
                'live_image_url' => trim((string) ($program['caratulaUrl'] ?? '')),
                'live_starts_at' => null,
                'live_ends_at' => null,
                'default_news_ids' => null,
                'live_news_ids' => null,
                'preview_news_ids' => null,
                'comentario_predeterminado' => trim((string) ($program['comentarioPredeterminado'] ?? '')),
                'red_social1_url' => trim((string) ($program['redSocial1Url'] ?? '')),
                'red_social2_url' => trim((string) ($program['redSocial2Url'] ?? '')),
                'activo' => (bool) ($program['activo'] ?? true),
                'archive_identifier' => trim((string) ($program['archiveIdentifier'] ?? '')),
                'vistas_archive' => (int) ($program['vistasArchive'] ?? 0),
                'escuchas_locales' => (int) ($program['escuchasLocales'] ?? 0),
                'vistas_totales' => (int) ($program['vistasTotales'] ?? 0),
                'stats_updated_at' => ! empty($program['statsUpdatedAt']) ? date('Y-m-d H:i:s', strtotime((string) $program['statsUpdatedAt'])) : null,
                'ruta_ftp' => trim((string) ($program['rutaCarpetaFtp'] ?? '')),
                'email_notificacion' => trim((string) ($program['correo'] ?? '')) ?: null,
                'email_copia_notificacion' => trim((string) ($program['correoCopia'] ?? '')) ?: null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            return substr($value, 0, 5);
        }

        return null;
    }
}
