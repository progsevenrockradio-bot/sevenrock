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
        $programId = (int) $request->query('program_id', 0);

        $program = $programId > 0
            ? Program::query()->find($programId)
            : (Program::query()->active()->orderBy('sort_order')->first()
                ?? Program::query()->orderBy('sort_order')->first());

        if (! $program) {
            return response()->json([
                'success' => false,
                'message' => 'Programa no encontrado',
            ], 404);
        }

        $masterProgramId = (int) ($program->master_program_id ?? $program->id ?? 0);
        $title = trim((string) ($program->titulo_programa ?: $program->name ?: ''));

        $episodeQuery = RadioProgram::query();
        if ($masterProgramId > 0) {
            $episodeQuery->where('master_program_id', $masterProgramId);
        }

        if ($title !== '') {
            $episodeQuery->orWhere('titulo_programa', $title);
        }

        $episode = $episodeQuery
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->first();

        $masterProgram = $masterProgramId > 0
            ? \App\Models\MasterProgram::query()->find($masterProgramId)
            : null;

        $description = trim((string) ($program->informacion_fija_programa ?: $program->description ?: ($masterProgram ? $masterProgram->description : '')));
        $host = trim((string) ($program->conductor ?: $program->host ?: ($masterProgram ? $masterProgram->host : '')));
        $genre = trim((string) ($program->genero_musical ?: ($masterProgram ? $masterProgram->genero : '')));
        $facebook = trim((string) ($program->facebook_url ?: ($masterProgram ? $masterProgram->red_social1_url : '')));
        $instagram = trim((string) ($program->instagram_url ?: ($masterProgram ? $masterProgram->red_social2_url : '')));
        
        $cover = trim((string) ($program->caratula_programa ?: $program->cover_url ?: $program->cover_image ?: ''));
        if ($cover === '' && $masterProgram) {
            $cover = trim((string) ($masterProgram->cover_url ?: $masterProgram->caratula_url ?: $masterProgram->live_image_url ?: ''));
        }

        $schedule = trim(implode(' · ', array_filter([
            trim((string) ($program->dia_transmision ?? '')),
            trim((string) ($program->hora_inicio ?? '')),
        ])));
        if ($schedule === '' && $masterProgram) {
            $schedule = trim((string) $masterProgram->schedule);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $program->getKey(),
                'name' => $program->titulo_programa ?: $program->name ?: '',
                'slug' => $program->slug ?: \Illuminate\Support\Str::slug($program->titulo_programa ?: $program->name ?: ''),
                'description' => $description,
                'host' => $host,
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
            ],
        ]);
    }
}
