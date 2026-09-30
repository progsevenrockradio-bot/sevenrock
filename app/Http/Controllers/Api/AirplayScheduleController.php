<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AirplaySchedule;
use App\Models\AirplayWeek;
use App\Models\Talent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AirplayScheduleController extends Controller
{
    public function store(Request ): JsonResponse
    {
         = ->validate([
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

          = strtoupper(['semana']);
         = now();

        DB::transaction(function () use (, , ): void {
             = collect(['bloques'])->filter(fn() => !empty(['es_novedad']) || !empty(['es_primer_pase']))->count();

            AirplayWeek::updateOrCreate(
                ['semana' => ],
                [
                    'generado_en'          => ['generado_en'] ?? null,
                    'recibido_en'          => ,
                    'pistas_total'         => count(['bloques']),
                    'novedades'            => ,
                    'hueco_publicidad_min' => ['hueco_publicidad_min'] ?? 8,
                ]
            );

            foreach (['bloques'] as ) {
                 = null;
                if (trim(['artista']) !== '') {
                     = Talent::query()
                        ->whereRaw('LOWER(band_name) = ?', [mb_strtolower(trim(['artista']))])
                        ->first();
                     = ->id;
                }

                   = ['email_sello'] ?? ['contacto_email'] ?? null;
                 = ['email_artista'] ?? null;

                AirplaySchedule::updateOrCreate(
                    [
                        'semana'   => ,
                        'dia'      => ['dia'],
                        'hora'     => ['hora'],
                        'posicion' => ['posicion'],
                    ],
                    [
                        'artista'          => ['artista'],
                        'titulo'           => ['titulo'],
                        'album'            => ['album'] ?? null,
                        'genero'           => ['genero'] ?? null,
                        'categoria'        => ['categoria'] ?? null,
                        'tipo_item'        => ['tipo_item'] ?? 'musica',
                        'duracion_seg'     => ['duracion_seg'] ?? null,
                        'duracion_fmt'     => ['duracion_fmt'] ?? null,
                        'es_novedad'       => (bool) (['es_novedad'] ?? false),
                        'es_primer_pase'   => (bool) (['es_primer_pase'] ?? false),
                        'sello'            => ['sello'] ?? null,
                        'email_sello'      => ,
                        'email_artista'    => ,
                        'contacto_email'   => ,
                        'spotify_track_id' => ['spotify_track_id'] ?? null,
                        'isrc'             => ['isrc'] ?? null,
                        'talent_id'        => ,
                        'sha1'             => ['sha1'] ?? null,
                    ]
                );
            }
        });

         = AirplayWeek::where('semana', )->first();

        return response()->json([
            'ok'        => true,
            'mensaje'   => 'Programación recibida correctamente',
            'semana'    => ,
            'pistas'    => ->pistas_total ?? 0,
            'novedades' => ->novedades ?? 0,
        ]);
    }
}
