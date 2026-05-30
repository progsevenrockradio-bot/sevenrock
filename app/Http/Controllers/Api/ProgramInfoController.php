<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\RadioProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProgramInfoController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $programId = (int) $request->query('program_id', 0);
        $programName = trim((string) $request->query('program_name', ''));
        $isLive = filter_var($request->query('is_live', '0'), FILTER_VALIDATE_BOOLEAN);

        // Strategy 1: Schedule-based lookup from master_programs (most reliable)
        $program = null;
        if ($isLive) {
            $program = $this->findProgramBySchedule();
        }

        // Strategy 2: Match by program_name (RadioBoss sends station_name)
        if (! $program && $programName !== '') {
            $program = Program::query()
                ->where('titulo_programa', 'LIKE', '%' . $programName . '%')
                ->orWhere('name', 'LIKE', '%' . $programName . '%')
                ->first();
        }

        // Strategy 3: By program_id
        if (! $program && $programId > 0) {
            $program = Program::query()->find($programId);
        }

        // Strategy 4: First active program
        if (! $program) {
            $program = Program::query()->active()->orderBy('sort_order')->first()
                ?? Program::query()->orderBy('sort_order')->first();
        }

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

        $description = trim((string) ($program->informacion_fija_programa ?: $program->description ?: ''));
        $host = trim((string) ($program->conductor ?: $program->host ?: ''));
        $genre = trim((string) ($program->genero_musical ?? ''));
        $facebook = trim((string) ($program->facebook_url ?? ''));
        $instagram = trim((string) ($program->instagram_url ?? ''));
        $cover = trim((string) ($program->caratula_programa ?: $program->cover_url ?: $program->cover_image ?: ''));
        $schedule = trim(implode(' · ', array_filter([
            trim((string) ($program->dia_transmision ?? '')),
            trim((string) ($program->hora_inicio ?? '')),
        ])));

        // Enrich schedule from master_programs if available
        $masterSchedule = $this->getMasterSchedule($program);
        if ($masterSchedule) {
            $schedule = $masterSchedule;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $program->getKey(),
                'name' => $program->titulo_programa ?: $program->name ?: '',
                'slug' => $program->slug ?: Str::slug($program->titulo_programa ?: $program->name ?: ''),
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

    /**
     * Find the program currently on air using master_programs schedule.
     * This is the most reliable method since it uses actual day + time + duration.
     */
    private function findProgramBySchedule(): ?Program
    {
        $now = Carbon::now('America/Caracas');
        $currentDay = Str::upper($now->locale('es')->dayName);
        $currentTime = $now->format('H:i:s');

        // Map localized day names to database values
        $dayMap = [
            'LUNES' => 'LUNES',
            'MARTES' => 'MARTES',
            'MIÉRCOLES' => 'MIERCOLES',
            'JUEVES' => 'JUEVES',
            'VIERNES' => 'VIERNES',
            'SÁBADO' => 'SABADO',
            'DOMINGO' => 'DOMINGO',
        ];

        $dia = $dayMap[$currentDay] ?? $currentDay;

        // Find active master programs for today
        $masters = DB::table('master_programs')
            ->where('dia_transmision', $dia)
            ->where('activo', 1)
            ->orderBy('hora_transmision')
            ->get();

        if ($masters->isEmpty()) {
            return null;
        }

        // Find which program is on air right now
        // A program is considered "on air" if: current time >= start time AND current time < start time + duration
        foreach ($masters as $master) {
            $startTime = $master->hora_transmision;
            $durationMinutes = (int) ($master->duracion_minutos ?? 120);

            if (empty($startTime)) {
                continue;
            }

            // Normalize start time format (handle HH:MM:SS or HH:MM)
            $startTime = substr((string) $startTime, 0, 5);

            $startCarbon = Carbon::createFromFormat('H:i', $startTime, 'America/Caracas');
            $endCarbon = $startCarbon->copy()->addMinutes($durationMinutes);

            // Handle overnight programs (e.g., 23:00 - 01:00)
            if ($now->between($startCarbon, $endCarbon) || ($endCarbon->lt($startCarbon) && ($now->gte($startCarbon) || $now->lte($endCarbon)))) {
                // Found the master program - find the corresponding radio_program
                $program = Program::query()
                    ->where('titulo_programa', $master->nombre)
                    ->orWhere('name', $master->nombre)
                    ->first();

                if ($program) {
                    return $program;
                }

                // If no exact match, try with master_program_id linking
                // (Some radio_programs have master_program_id pointing to master_programs.id)
                $program = Program::query()
                    ->where('master_program_id', $master->id)
                    ->first();

                if ($program) {
                    return $program;
                }

                // Last resort: try partial match
                $program = Program::query()
                    ->whereRaw("UPPER(titulo_programa) LIKE ?", ['%' . strtoupper($master->nombre) . '%'])
                    ->first();

                return $program;
            }
        }

        return null;
    }

    /**
     * Get schedule info from master_programs for display.
     */
    private function getMasterSchedule(Program $program): ?string
    {
        $master = DB::table('master_programs')
            ->where('nombre', $program->titulo_programa ?? $program->name ?? '')
            ->orWhere('id', (int) ($program->master_program_id ?? 0))
            ->first();

        if (! $master) {
            return null;
        }

        $parts = array_filter([
            trim((string) ($master->dia_transmision ?? '')),
            trim((string) ($master->hora_transmision ?? '')),
            $master->duracion_minutos ? $master->duracion_minutos . ' min' : '',
        ]);

        return implode(' · ', $parts);
    }
}