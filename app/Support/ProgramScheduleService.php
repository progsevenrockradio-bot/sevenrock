<?php

namespace App\Support;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\ThemeSetting;
use App\Support\ExternalHttp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ProgramScheduleService
{
    private const DAY_ORDER = [
        'LUNES' => 1,
        'MARTES' => 2,
        'MIERCOLES' => 3,
        'JUEVES' => 4,
        'VIERNES' => 5,
        'SABADO' => 6,
        'DOMINGO' => 7,
    ];

    public function resolve(int $limit = 5): array
    {
        $theme = ThemeSetting::current();
        $scheduledPrograms = $this->programsWithNextStart();

        if ($scheduledPrograms->isNotEmpty()) {
            $current = $this->currentProgramFromSchedule($scheduledPrograms) ?? $scheduledPrograms->first()['program'];
            $upcoming = $this->upcomingProgramsFromSchedule($scheduledPrograms, $current, $limit);

            return [
                ...$this->programPayload($current, $theme, $this->programIsLiveNow($current) ? 'On air' : 'On deck'),
                'upcoming' => $this->mapUpcoming($upcoming, $theme),
            ];
        }

        return $this->fallback();
    }

    public function fallback(): array
    {
        return [
            'label' => 'Próximo programa',
            'subtitle' => 'Avance editorial del siguiente bloque en parrilla',
            'title' => 'PROGRAMACIÓN',
            'host' => 'Seven Rock Radio',
            'location' => 'Próxima emisión',
            'schedule' => 'Programación continua',
            'show' => 'Seven Rock Radio',
            'timezone' => 'America/Caracas',
            'summary' => 'La grilla editorial se muestra aquí cuando existe programación maestro o cuando RadioBOSS devuelve la siguiente emisión.',
            'image' => 'assets/lucille/pedalboard-1511069_1920.jpg',
            'badge' => 'On deck',
            'button' => ['label' => 'Ver programación', 'url' => route('programs')],
            'upcoming' => [
                [
                    ...$this->fallbackProgramPayload('PROGRAMACIÓN', 'Seven Rock Radio', 'Próxima emisión', 'assets/lucille/microphone-1206364_1920.jpg'),
                    'time' => 'Cargando',
                ],
                [
                    ...$this->fallbackProgramPayload('SEVEN ROCK RADIO', 'Seven Rock Radio', 'Programación continua', 'assets/lucille/pedalboard-1511069_1920.jpg'),
                    'time' => 'Cargando',
                ],
                [
                    ...$this->fallbackProgramPayload('MULTIMEDIA', 'Seven Rock Radio', 'Bloque especial', 'assets/lucille/music-1284505_1920.jpg'),
                    'time' => 'Cargando',
                ],
                [
                    ...$this->fallbackProgramPayload('EVENTOS', 'Seven Rock Radio', 'Agenda en vivo', 'assets/lucille/live-slider-bg.jpg'),
                    'time' => 'Cargando',
                ],
                [
                    ...$this->fallbackProgramPayload('ENTREVISTAS', 'Seven Rock Radio', 'Cápsulas y especial', 'assets/lucille/guitar-1758005_1920.jpg'),
                    'time' => 'Cargando',
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, MasterProgram>
     */
    private function masterPrograms(): Collection
    {
        if (! Schema::hasTable('master_programs')) {
            return collect();
        }

        return MasterProgram::query()
            ->when(Schema::hasColumn('master_programs', 'activo'), fn ($query) => $query->where('activo', true))
            ->get()
            ->sort(function (MasterProgram $left, MasterProgram $right): int {
                return $this->sortTuple($left) <=> $this->sortTuple($right);
            })
            ->values();
    }

    private function sortTuple(MasterProgram $program): array
    {
        $today = now('America/Caracas')->dayOfWeekIso;
        $dayNumber = $this->dayNumber((string) $program->dia_transmision) ?? 8;
        $delta = ($dayNumber - $today + 7) % 7;
        $timeRank = $this->timeRank((string) $program->hora_transmision);

        if ($delta === 0 && $timeRank === PHP_INT_MAX) {
            $timeRank = 0;
        }

        return [$delta, $timeRank, (int) $program->getKey()];
    }

    private function timeRank(string $value): int
    {
        $hm = $this->parseScheduleTime($value);
        if (! $hm) {
            return PHP_INT_MAX;
        }

        return ((int) $hm[0] * 60) + (int) $hm[1];
    }

    private function dayNumber(string $day): ?int
    {
        $day = mb_strtoupper(\Illuminate\Support\Str::ascii(trim($day)));

        return self::DAY_ORDER[$day] ?? null;
    }

    private function parseScheduleTime(string $time): ?array
    {
        $time = trim($time);
        if ($time === '') {
            return null;
        }

        $time = str_replace('.', ':', $time);
        $parts = array_values(array_filter(explode(':', $time), static fn ($part) => trim($part) !== ''));

        if ($parts === []) {
            return null;
        }

        $hour = (int) preg_replace('/\D+/', '', (string) ($parts[0] ?? ''));
        $minute = (int) preg_replace('/\D+/', '', (string) ($parts[1] ?? '0'));

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return [$hour, $minute];
    }

    /**
     * @param Collection<int, MasterProgram> $programs
     */
    private function currentProgram(Collection $programs): ?MasterProgram
    {
        $now = now('America/Caracas');

        $scheduled = $programs
            ->map(function (MasterProgram $program) use ($now): array {
                return [
                    'program' => $program,
                    'next_start' => $this->nextProgramStart($program, $now),
                ];
            })
            ->filter(fn (array $item): bool => $item['next_start'] instanceof Carbon)
            ->sort(function (array $left, array $right): int {
                return ($left['next_start']?->timestamp ?? PHP_INT_MAX) <=> ($right['next_start']?->timestamp ?? PHP_INT_MAX);
            })
            ->values();

        return $this->currentProgramFromSchedule($scheduled);
    }

    /**
     * @param Collection<int, MasterProgram> $programs
     * @return Collection<int, MasterProgram>
     */
    private function upcomingPrograms(Collection $programs, ?MasterProgram $current, int $limit): Collection
    {
        if ($programs->isEmpty()) {
            return collect();
        }

        $indexed = $programs->values();

        if (! $current) {
            return $indexed->take($limit);
        }

        $currentIndex = $indexed->search(fn (MasterProgram $program): bool => (int) $program->getKey() === (int) $current->getKey());
        if ($currentIndex === false) {
            return $indexed->take($limit);
        }

        return $indexed
            ->slice($currentIndex + 1)
            ->concat($indexed->slice(0, $currentIndex))
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array{program: MasterProgram, next_start: Carbon|null}>
     */
    private function programsWithNextStart(): Collection
    {
        if (! Schema::hasTable('master_programs')) {
            return collect();
        }

        return $this->masterPrograms()
            ->map(function (MasterProgram $program): array {
                return [
                    'program' => $program,
                    'next_start' => $this->nextProgramStart($program),
                ];
            })
            ->sort(function (array $left, array $right): int {
                $leftTs = $left['next_start']?->timestamp ?? PHP_INT_MAX;
                $rightTs = $right['next_start']?->timestamp ?? PHP_INT_MAX;

                if ($leftTs === $rightTs) {
                    return mb_strtolower(trim((string) $left['program']->name)) <=> mb_strtolower(trim((string) $right['program']->name));
                }

                return $leftTs <=> $rightTs;
            })
            ->values();
    }

    /**
     * @param Collection<int, array{program: MasterProgram, next_start: Carbon|null}> $programs
     */
    private function currentProgramFromSchedule(Collection $programs): ?MasterProgram
    {
        if ($programs->isEmpty()) {
            return null;
        }

        $now = now('America/Caracas');

        $live = $programs->first(fn (array $item): bool => $this->programIsLiveNow($item['program'], $now));
        if (is_array($live) && $live['program'] instanceof MasterProgram) {
            return $live['program'];
        }

        $upcoming = $programs->first(fn (array $item): bool => $item['next_start'] instanceof Carbon && $item['next_start']->greaterThan($now));
        if (is_array($upcoming) && $upcoming['program'] instanceof MasterProgram) {
            return $upcoming['program'];
        }

        $first = $programs->first();

        return is_array($first) && $first['program'] instanceof MasterProgram ? $first['program'] : null;
    }

    /**
     * @param Collection<int, array{program: MasterProgram, next_start: Carbon|null}> $programs
     * @return Collection<int, MasterProgram>
     */
    private function upcomingProgramsFromSchedule(Collection $programs, ?MasterProgram $current, int $limit): Collection
    {
        if ($programs->isEmpty()) {
            return collect();
        }

        $indexed = $programs->values();

        if (! $current) {
            return $indexed->take($limit)->pluck('program')->values();
        }

        $currentIndex = $indexed->search(fn (array $item): bool => (int) $item['program']->getKey() === (int) $current->getKey());
        if ($currentIndex === false) {
            return $indexed->take($limit)->pluck('program')->values();
        }

        return $indexed
            ->slice($currentIndex + 1)
            ->concat($indexed->slice(0, $currentIndex))
            ->take($limit)
            ->pluck('program')
            ->values();
    }

    private function programIsLiveNow(MasterProgram $program, ?Carbon $now = null): bool
    {
        $now ??= now($this->programTimezone($program));
        $timezone = $this->programTimezone($program);
        $now = $now->copy()->setTimezone($timezone);

        if ($program->live_starts_at && $program->live_ends_at) {
            $startsAt = $program->live_starts_at->copy()->setTimezone($timezone);
            $endsAt = $program->live_ends_at->copy()->setTimezone($timezone);
            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                $endsAt = $endsAt->copy()->addDay();
            }

            return $now->between($startsAt, $endsAt);
        }

        $dayNumber = $this->dayNumber((string) $program->dia_transmision);
        if ($dayNumber === null) {
            return false;
        }

        $hm = $this->parseScheduleTime((string) $program->hora_transmision);
        if (! $hm) {
            return true;
        }

        [$hour, $minute] = $hm;
        $daysSinceSchedule = ((int) $now->dayOfWeekIso - $dayNumber + 7) % 7;
        $start = $now->copy()->startOfDay()->subDays($daysSinceSchedule)->setTime($hour, $minute, 0);
        $duration = max(15, (int) ($program->duracion_minutos ?? 120));
        $end = $start->copy()->addMinutes($duration);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->copy()->addDay();
        }

        return $now->between($start, $end);
    }

    private function nextProgramStart(MasterProgram $program, ?Carbon $reference = null): ?Carbon
    {
        $timezone = $this->programTimezone($program);
        $now = ($reference ?? now($timezone))->copy()->setTimezone($timezone);

        if ($program->live_starts_at) {
            $liveStart = $program->live_starts_at->copy()->setTimezone($timezone);
            if ($liveStart->greaterThan($now)) {
                return $liveStart;
            }
        }

        $day = $this->dayNumber((string) $program->dia_transmision);
        $time = $this->parseScheduleTime((string) $program->hora_transmision);

        if ($day === null || ! $time) {
            return null;
        }

        [$hour, $minute] = $time;
        $daysAhead = ($day - (int) $now->dayOfWeekIso + 7) % 7;
        $candidate = $now->copy()->startOfDay()->addDays($daysAhead)->setTime($hour, $minute, 0);

        if ($candidate->lessThanOrEqualTo($now)) {
            $candidate->addWeek();
        }

        $episode = $this->episodeForProgramOnDate($program, $candidate->toDateString());
        if ($episode && $episode->hora_inicio) {
            $episodeHm = $this->parseScheduleTime((string) $episode->hora_inicio);
            if ($episodeHm) {
                [$episodeHour, $episodeMinute] = $episodeHm;
                $candidate = $candidate->copy()->setTime($episodeHour, $episodeMinute, 0);

                if ($candidate->lessThanOrEqualTo($now)) {
                    $candidate->addWeek();
                }
            }
        }

        return $candidate;
    }

    private function summaryFor(MasterProgram $program): string
    {
        $parts = array_filter([
            trim((string) $program->description),
            trim((string) $program->comentario_predeterminado),
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return trim(sprintf(
            'El siguiente bloque programado es %s en %s.',
            $program->name ?: 'programación editorial',
            $program->schedule ?: 'horario continuo'
        ));
    }

    private function imageForProgram(MasterProgram $program, ThemeSetting $theme): string
    {
        if ($program->cover_url !== '') {
            return $program->cover_url;
        }

        return $theme->home_video_image_path ?: $theme->hero_slide_primary_path ?: 'assets/lucille/pedalboard-1511069_1920.jpg';
    }

    /**
     * @param Collection<int, MasterProgram> $programs
     * @return array<int, array{time:string,title:string,host:string,image:string}>
     */
    private function mapUpcoming(Collection $programs, ThemeSetting $theme): array
    {
        return $programs
            ->take(5)
            ->map(function (MasterProgram $program) use ($theme): array {
                return [
                    ...$this->programPayload($program, $theme, 'On deck'),
                    'time' => $program->schedule ?: 'Próxima emisión',
                ];
            })
            ->values()
            ->all();
    }

    private function programPayload(MasterProgram $program, ThemeSetting $theme, string $badge): array
    {
        return [
            'label' => 'Próximo programa',
            'subtitle' => 'Avance editorial del siguiente bloque en parrilla',
            'title' => mb_strtoupper($program->name ?: 'PROGRAMACIÓN'),
            'title_html' => formatear_titulo(mb_strtoupper($program->name ?: 'PROGRAMACIÓN')),
            'host' => $program->host ?: 'Seven Rock Radio',
            'location' => $program->schedule ?: 'Próxima emisión',
            'schedule' => $program->schedule ?: 'Próxima emisión',
            'show' => $program->name ?: 'Programación',
            'timezone' => $program->timezone ?: 'America/Caracas',
            'summary' => $this->summaryFor($program),
            'image' => $this->imageForProgram($program, $theme),
            'badge' => $badge,
            'button' => [
                'label' => 'Ver programación',
                'url' => route('programs'),
            ],
        ];
    }

    private function fallbackProgramPayload(string $title, string $host, string $schedule, string $image): array
    {
        return [
            'label' => 'Próximo programa',
            'subtitle' => 'Avance editorial del siguiente bloque en parrilla',
            'title' => $title,
            'title_html' => formatear_titulo($title),
            'host' => $host,
            'location' => $schedule,
            'schedule' => $schedule,
            'show' => $title,
            'timezone' => 'America/Caracas',
            'summary' => sprintf('Programación destacada de %s.', $title),
            'image' => $image,
            'badge' => 'On deck',
            'button' => [
                'label' => 'Ver programación',
                'url' => route('programs'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function remoteUpcomingEvents(int $limit): array
    {
        $apiUrl = trim((string) config('player.radioboss.api_url', ''));
        $stationId = trim((string) config('player.radioboss.station_id', ''));
        $apiKey = trim((string) config('player.radioboss.api_key', ''));

        if ($apiUrl === '' || $stationId === '' || $apiKey === '') {
            return [];
        }

        try {
            $response = ExternalHttp::client()->connectTimeout(1)
                ->timeout(2)
                ->acceptJson()
                ->get(rtrim($apiUrl, '/') . '/api/getupcomingevents/' . $stationId, [
                    'key' => $apiKey,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            if (! is_array($data)) {
                return [];
            }

            $items = collect($data)
                ->filter('is_array')
                ->map(function (array $event): array {
                    $event['_sort'] = $this->parseRemoteDate((string) ($event['nextstart'] ?? ''));

                    return $event;
                })
                ->sortBy(fn (array $event): int => $event['_sort'] ?? PHP_INT_MAX)
                ->values()
                ->map(function (array $event): array {
                    unset($event['_sort']);

                    return $event;
                })
                ->all();

            return array_slice($items, 0, max(1, $limit));
        } catch (\Throwable) {
            return [];
        }
    }

    private function warmRemoteCache(int $limit): void
    {
        // Cache warmup disabled on public page rendering to avoid file cache failures.
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function resolveFromRemoteEvents(array $events, ThemeSetting $theme, int $limit): array
    {
        $primary = $events[0] ?? [];
        $primaryTitle = trim((string) ($primary['title'] ?? ''));
        $primaryStart = trim((string) ($primary['nextstart'] ?? ''));
        $primarySchedule = $this->formatRemoteSchedule($primaryStart);

        return [
            'label' => 'Próximo programa',
            'subtitle' => 'Avance editorial del siguiente bloque en parrilla',
            'title' => mb_strtoupper($primaryTitle !== '' ? $primaryTitle : 'PROGRAMACIÓN'),
            'host' => 'Seven Rock Radio',
            'location' => $primarySchedule !== '' ? $primarySchedule : 'Próxima emisión',
            'schedule' => $primarySchedule !== '' ? $primarySchedule : 'Programación continua',
            'show' => $primaryTitle !== '' ? $primaryTitle : 'Programación',
            'timezone' => 'America/Caracas',
            'summary' => sprintf(
                'La próxima emisión en vivo es %s%s.',
                $primaryTitle !== '' ? $primaryTitle : 'programación',
                $primaryStart !== '' ? ' (' . $primarySchedule . ')' : ''
            ),
            'image' => $theme->home_video_image_path ?: $theme->hero_slide_primary_path ?: 'assets/lucille/pedalboard-1511069_1920.jpg',
            'badge' => 'On deck',
            'button' => [
                'label' => 'Ver programación',
                'url' => route('programs'),
            ],
            'upcoming' => $this->mapRemoteUpcoming($events, $theme, $limit),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array{time:string,title:string,host:string,image:string}>
     */
    private function mapRemoteUpcoming(array $events, ThemeSetting $theme, int $limit): array
    {
        return collect($events)
            ->map(function (array $event) use ($theme): array {
                $title = trim((string) ($event['title'] ?? 'PROGRAMACIÓN'));
                $time = $this->formatRemoteSchedule(trim((string) ($event['nextstart'] ?? '')));

                return [
                    'time' => $time !== '' ? $time : 'Próxima emisión',
                    'title' => mb_strtoupper($title !== '' ? $title : 'PROGRAMACIÓN'),
                    'host' => 'Seven Rock Radio',
                    'image' => $theme->home_video_image_path ?: $theme->hero_slide_primary_path ?: 'assets/lucille/microphone-1206364_1920.jpg',
                ];
            })
            ->take(max(1, $limit))
            ->values()
            ->all();
    }

    private function formatRemoteSchedule(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value, 'America/Caracas')->translatedFormat('D · d M Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function parseRemoteDate(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return PHP_INT_MAX;
        }

        try {
            return Carbon::parse($value, 'America/Caracas')->getTimestamp();
        } catch (\Throwable) {
            return PHP_INT_MAX;
        }
    }

    private function programTimezone(MasterProgram $program): string
    {
        $timezone = trim((string) ($program->timezone ?: 'America/Caracas'));

        return $timezone !== '' ? $timezone : 'America/Caracas';
    }

    private function episodeForProgramOnDate(MasterProgram $program, ?string $date = null): ?RadioProgram
    {
        if (! class_exists(RadioProgram::class) || ! Schema::hasTable('radio_programs')) {
            return null;
        }

        $date ??= now($this->programTimezone($program))->toDateString();

        return RadioProgram::query()
            ->whereDate('fecha_emision', $date)
            ->where(function ($query) use ($program): void {
                $query->where('master_program_id', $program->getKey())
                    ->orWhere('titulo_programa', (string) $program->nombre);
            })
            ->latest('numero_episodio')
            ->latest('id')
            ->first();
    }
}
