<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\RadioProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramInfoController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $programId = (int) $request->query('program_id', '0');
        $programName = trim((string) $request->query('program_name', ''));

        $program = null;
        $masterProgram = null;

        if ($programId > 0) {
            $program = Program::query()->find($programId);
            if (! $program && class_exists(\App\Models\MasterProgram::class)) {
                $masterProgram = \App\Models\MasterProgram::query()->find($programId);
            }
        }

        if (! $program && ! $masterProgram && $programName !== '') {
            if (class_exists(\App\Models\MasterProgram::class)) {
                $masterProgram = \App\Models\MasterProgram::query()
                    ->where('nombre', 'like', "%{$programName}%")
                    ->first();
            }
            if (! $masterProgram) {
                $program = Program::query()
                    ->where('titulo_programa', 'like', "%{$programName}%")
                    ->first();
            }
        }

        // Si estamos en entorno local y hay simulación activa, extraer el programa del estado simulado
        if (app()->environment('local') && \Illuminate\Support\Facades\Cache::has('dev_player_simulated_status')) {
            $sim = \Illuminate\Support\Facades\Cache::get('dev_player_simulated_status');
            if (is_array($sim) && !empty($sim['program_name'])) {
                $simProg = is_array($sim['program'] ?? null) ? $sim['program'] : [];
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $simProg['id'] ?? $sim['program_id'] ?? 901,
                        'name' => $simProg['name'] ?? $simProg['nombre'] ?? $sim['program_name'],
                        'title' => $simProg['name'] ?? $simProg['nombre'] ?? $sim['program_name'],
                        'description' => $simProg['descripcion'] ?? $simProg['description'] ?? $sim['program_description'] ?? '',
                        'host' => $simProg['host'] ?? $simProg['conductor'] ?? $sim['track']['program_host'] ?? '',
                        'country' => $simProg['country'] ?? $simProg['pais'] ?? $sim['track']['program_country'] ?? null,
                        'genre' => 'Rock / Metal',
                        'schedule' => $simProg['horario'] ?? $sim['track']['program_schedule'] ?? '',
                        'cover' => $simProg['cover'] ?? $sim['track']['cover'] ?? '',
                        'social_links' => [],
                        'episode' => $sim['track']['meta_source'] === 'program:emission' ? [
                            'title' => $sim['track']['title'] ?? '',
                            'description' => $sim['track']['program_description'] ?? '',
                            'episode_number' => 12,
                            'guest_bio' => '',
                        ] : null,
                    ],
                ]);
            }
        }

        if (! $program && ! $masterProgram) {
            try {
                $playerService = app(\App\Services\RadioPlayerService::class);
                $status = $playerService->resolve();
                $resolvedId = (int) ($status['program_id'] ?? $status['track']['program_id'] ?? 0);
                $resolvedName = trim((string) ($status['program_name'] ?? $status['track']['program_name'] ?? ''));

                if ($resolvedId > 0) {
                    $program = Program::query()->find($resolvedId);
                    if (! $program && class_exists(\App\Models\MasterProgram::class)) {
                        $masterProgram = \App\Models\MasterProgram::query()->find($resolvedId);
                    }
                }

                if (! $program && ! $masterProgram && $resolvedName !== '') {
                    if (class_exists(\App\Models\MasterProgram::class)) {
                        $masterProgram = \App\Models\MasterProgram::query()
                            ->where('nombre', 'like', "%{$resolvedName}%")
                            ->first();
                    }
                    if (! $masterProgram) {
                        $program = Program::query()
                            ->where('titulo_programa', 'like', "%{$resolvedName}%")
                            ->first();
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if (! $program && ! $masterProgram) {
            return response()->json([
                'success' => false,
                'message' => 'No hay ningún programa especial en vivo en este momento.',
            ], 404);
        }

        $masterProgramId = (int) ($program?->master_program_id ?? $masterProgram?->id ?? 0);
        $title = trim((string) ($program?->titulo_programa ?: $program?->name ?: $masterProgram?->name ?: $masterProgram?->nombre ?: ''));

        $episode = null;
        if ($masterProgramId > 0) {
            $episode = RadioProgram::query()
                ->where('master_program_id', $masterProgramId)
                ->orderByDesc('fecha_emision')
                ->orderByDesc('id')
                ->first();
        } elseif ($title !== '') {
            $episode = RadioProgram::query()
                ->where('titulo_programa', $title)
                ->orderByDesc('fecha_emision')
                ->orderByDesc('id')
                ->first();
        }

        if (! $masterProgram && $masterProgramId > 0) {
            $masterProgram = \App\Models\MasterProgram::query()->find($masterProgramId);
        }

        $description = trim((string) ($program?->informacion_fija_programa ?: $program?->description ?: ($masterProgram ? $masterProgram->description : '')));
        $host = trim((string) ($program?->conductor ?: $program?->host ?: ($masterProgram ? ($masterProgram->host ?: $masterProgram->conductor) : '')));
        $pais = trim((string) ($episode?->pais ?: $program?->pais ?: ($masterProgram ? $masterProgram->pais : '')));
        $genre = trim((string) ($program?->genero_musical ?: ($masterProgram ? $masterProgram->genero : '')));
        $facebook = trim((string) ($program?->facebook_url ?: ($masterProgram ? $masterProgram->red_social1_url : '')));
        $instagram = trim((string) ($program?->instagram_url ?: ($masterProgram ? $masterProgram->red_social2_url : '')));
        
        $cover = trim((string) ($program?->caratula_programa ?: $program?->cover_url ?: $program?->cover_image ?: ''));
        if ($cover === '' && $masterProgram) {
            $cover = trim((string) ($masterProgram->cover_url ?: $masterProgram->caratula_url ?: $masterProgram->live_image_url ?: ''));
        }

        $schedule = trim(implode(' · ', array_filter([
            trim((string) ($program?->dia_transmision ?? '')),
            trim((string) ($program?->hora_inicio ?? '')),
        ])));
        if ($schedule === '' && $masterProgram) {
            $schedule = trim((string) $masterProgram->schedule);
        }

        $resolvedName = $program?->titulo_programa ?: $program?->name ?: $masterProgram?->name ?: $masterProgram?->nombre ?: '';
        $resolvedSlug = $program?->slug ?: $masterProgram?->slug ?: \Illuminate\Support\Str::slug($resolvedName);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $program?->getKey() ?? $masterProgram?->getKey(),
                'name' => $resolvedName,
                'slug' => $resolvedSlug,
                'description' => $description,
                'host' => $host,
                'country' => $pais !== '' ? $pais : null,
                'schedule' => $schedule,
                'cover' => $cover,
                'genre' => $genre,
                'social_links' => [
                    'facebook' => $facebook !== '' ? $facebook : null,
                    'instagram' => $instagram !== '' ? $instagram : null,
                ],
                'episode' => $episode ? [
                    'id' => $episode->getKey(),
                    'title' => trim((string) ($episode->live_title ?: $episode->titulo_programa ?: $episode->name ?: $title)),
                    'guest_bio' => trim((string) ($episode->biografia_invitado ?: '')),
                    'guest_image' => trim((string) ($episode->imagen_invitado ?: '')),
                    'description' => trim((string) ($episode->live_description ?: $episode->comentario_episodio ?: $episode->resena ?: '')),
                    'date' => optional($episode->fecha_emision)->toIso8601String(),
                    'episode_number' => $episode->numero_episodio ?? null,
                ] : null,
                'is_live' => true,
                'emisiones' => $masterProgram
                    ? $masterProgram->emisiones()->activas()->get()->map(fn ($em) => [
                        'tipo'             => $em->tipo,
                        'etiqueta'         => $em->etiqueta,
                        'dia_semana'       => $em->dia_semana,
                        'hora_inicio'      => $em->horaFormateada(),
                        'duracion_minutos' => $em->duracion_minutos,
                        'enlace'           => $em->enlace,
                        'url_podcast'      => $em->url_podcast,
                    ])->values()->toArray()
                    : [],
            ],
        ]);
    }
}
