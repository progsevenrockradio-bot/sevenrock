<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AirplaySchedule;
use App\Models\AirplayWeek;
use App\Models\Talent;
use App\Services\ArtistEmailMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AirplayScheduleController extends Controller
{
    public function __construct(
        protected ArtistEmailMatcher $matcher
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semana'               => ['required', 'string', 'regex:/^\d{4}-W\d{2}$/i'],
            'generado_en'          => ['nullable', 'date'],
            'hueco_publicidad_min' => ['nullable', 'integer', 'min:0', 'max:60'],
            'bloques'              => ['required', 'array', 'min:1'],
            'bloques.*.dia'        => ['required', 'integer', 'min:1', 'max:7'],
            'bloques.*.hora'       => ['required', 'integer', 'min:0', 'max:23'],
            'bloques.*.posicion'   => ['required', 'integer', 'min:1'],
            'bloques.*.artista'    => ['required', 'string', 'max:190'],
            'bloques.*.titulo'     => ['required', 'string', 'max:190'],
            'bloques.*.album'      => ['nullable', 'string', 'max:190'],
            'bloques.*.genero'     => ['nullable', 'string', 'max:100'],
            'bloques.*.categoria'  => ['nullable', 'string', 'max:100'],
            'bloques.*.tipo_item'   => ['nullable', 'string', 'max:50'],
            'bloques.*.duracion_seg'=> ['nullable', 'integer', 'min:0'],
            'bloques.*.duracion_fmt'=> ['nullable', 'string', 'max:10'],
            'bloques.*.es_novedad' => ['nullable', 'boolean'],
            'bloques.*.es_primer_pase' => ['nullable', 'boolean'],
            'bloques.*.sello'      => ['nullable', 'string', 'max:190'],
            'bloques.*.email_sello'=> ['nullable', 'email', 'max:190'],
            'bloques.*.email_artista' => ['nullable', 'email', 'max:190'],
            'bloques.*.contacto_email' => ['nullable', 'email', 'max:190'],
            'bloques.*.spotify_track_id' => ['nullable', 'string', 'max:100'],
            'bloques.*.isrc'       => ['nullable', 'string', 'max:50'],
            'bloques.*.sha1'       => ['nullable', 'string', 'max:40'],
        ]);

        $semana = strtoupper($validated['semana']);
        $ahora = now();

        DB::transaction(function () use ($validated, $semana, $ahora): void {
            $novedades = collect($validated['bloques'])->filter(fn($b) => !empty($b['es_novedad']) || !empty($b['es_primer_pase']))->count();

            AirplayWeek::updateOrCreate(
                ['semana' => $semana],
                [
                    'generado_en'          => $validated['generado_en'] ?? null,
                    'recibido_en'          => $ahora,
                    'pistas_total'         => count($validated['bloques']),
                    'novedades'            => $novedades,
                    'hueco_publicidad_min' => $validated['hueco_publicidad_min'] ?? 8,
                ]
            );

            foreach ($validated['bloques'] as $bloque) {
                $talentId = null;
                if (trim($bloque['artista']) !== '') {
                    $talent = Talent::query()
                        ->whereRaw('LOWER(band_name) = ?', [mb_strtolower(trim($bloque['artista']))])
                        ->first();
                    $talentId = $talent?->id;
                }

                $emailSello = $bloque['email_sello'] ?? $bloque['contacto_email'] ?? null;
                $emailArtista = $bloque['email_artista'] ?? null;

                if (empty($emailArtista) || empty($emailSello)) {
                    $matched = $this->matcher->matchArtist($bloque['artista'], $bloque['sello'] ?? null);
                    if (empty($emailArtista) && !empty($matched['email_artista'])) {
                        $emailArtista = $matched['email_artista'];
                    }
                    if (empty($emailSello) && !empty($matched['email_sello'])) {
                        $emailSello = $matched['email_sello'];
                    }
                }

                AirplaySchedule::updateOrCreate(
                    [
                        'semana'   => $semana,
                        'dia'      => $bloque['dia'],
                        'hora'     => $bloque['hora'],
                        'posicion' => $bloque['posicion'],
                    ],
                    [
                        'artista'          => $bloque['artista'],
                        'titulo'           => $bloque['titulo'],
                        'album'            => $bloque['album'] ?? null,
                        'genero'           => $bloque['genero'] ?? null,
                        'categoria'        => $bloque['categoria'] ?? null,
                        'tipo_item'        => $bloque['tipo_item'] ?? 'musica',
                        'duracion_seg'     => $bloque['duracion_seg'] ?? null,
                        'duracion_fmt'     => $bloque['duracion_fmt'] ?? null,
                        'es_novedad'       => (bool) ($bloque['es_novedad'] ?? false),
                        'es_primer_pase'   => (bool) ($bloque['es_primer_pase'] ?? false),
                        'sello'            => $bloque['sello'] ?? null,
                        'email_sello'      => $emailSello,
                        'email_artista'    => $emailArtista,
                        'contacto_email'   => $bloque['contacto_email'] ?? $emailSello,
                        'spotify_track_id' => $bloque['spotify_track_id'] ?? null,
                        'isrc'             => $bloque['isrc'] ?? null,
                        'talent_id'        => $talentId,
                        'sha1'             => $bloque['sha1'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'ok'      => true,
            'message' => 'Programación recibida correctamente.',
            'semana'  => $semana,
            'total'   => count($validated['bloques']),
        ]);
    }
}
