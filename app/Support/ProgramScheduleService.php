<?php

namespace App\Support;

use App\Models\Event;
use App\Models\ThemeSetting;
use Illuminate\Support\Collection;

class ProgramScheduleService
{
    public function resolve(int $limit = 3): array
    {
        $theme = ThemeSetting::current();
        $events = Event::query()
            ->upcoming()
            ->orderBy('starts_at')
            ->limit(max($limit + 1, 4))
            ->get();

        $program = $events->first() ?? Event::query()->orderBy('starts_at')->first();

        if (! $program) {
            return $this->fallback();
        }

        return [
            'label' => 'Próximo programa',
            'subtitle' => 'Avance editorial del siguiente bloque en parrilla',
            'title' => mb_strtoupper($program->title),
            'host' => $program->venue ?: $program->location ?: 'Seven Rock Radio',
            'location' => $program->location ?: 'Próxima emisión',
            'schedule' => $program->starts_at?->format('l · H:i') ?? 'Próxima emisión',
            'show' => $program->venue ?: $program->title,
            'timezone' => 'Zona America/Caracas',
            'summary' => $this->summaryFor($program),
            'image' => $this->imageForEvent($program->slug, $theme),
            'badge' => 'On deck',
            'button' => [
                'label' => 'Ver evento',
                'url' => route('events.single', $program->slug),
            ],
            'upcoming' => $this->mapUpcoming($events->skip(1), $theme),
        ];
    }

    public function fallback(): array
    {
        return [
            'label' => 'Próximo programa',
            'subtitle' => 'Avance editorial del siguiente bloque en parrilla',
            'title' => 'POLYVERSUM',
            'host' => 'Seven Rock Radio',
            'location' => 'Próxima emisión',
            'schedule' => 'Miércoles · 19:00',
            'show' => 'Metal Under',
            'timezone' => 'Zona America/Caracas',
            'summary' => 'Conducción prevista para este turno. Polyversum es una pieza editorial en vivo que mezcla catálogo, contexto y una presentación clara del siguiente bloque.',
            'image' => 'assets/lucille/pedalboard-1511069_1920.jpg',
            'badge' => 'On deck',
            'button' => ['label' => 'Leer más', 'url' => '#'],
            'upcoming' => [
                ['time' => 'Hoy miércoles · 22:00', 'title' => 'ESTACION ROCK', 'host' => 'Claudio E. Laguna', 'image' => 'assets/lucille/microphone-1206364_1920.jpg'],
                ['time' => 'Mañana jueves · 17:00', 'title' => 'POLUCION', 'host' => 'Yury Fernández', 'image' => 'assets/lucille/guitar-1245856_1920.jpg'],
                ['time' => 'Mañana jueves · 21:00', 'title' => 'ROCK MADE IN AMAZON', 'host' => 'Claudio Wallace', 'image' => 'assets/lucille/music-1284505_1920.jpg'],
            ],
        ];
    }

    private function summaryFor(Event $event): string
    {
        $location = $event->location ?: 'próxima emisión';

        return trim(sprintf(
            'El siguiente bloque programado es %s desde %s.',
            $event->title,
            $location
        ));
    }

    private function imageForEvent(?string $slug, ThemeSetting $theme): string
    {
        $map = [
            'wakestock-festival' => 'assets/lucille/microphone-1206364_1920.jpg',
            'rockness-festival' => 'assets/lucille/pedalboard-1511069_1920.jpg',
            'coachella-music-festival' => 'assets/lucille/music-1284505_1920.jpg',
        ];

        if ($slug && isset($map[$slug])) {
            return $map[$slug];
        }

        return $theme->home_video_image_path ?: $theme->hero_slide_primary_path ?: 'assets/lucille/pedalboard-1511069_1920.jpg';
    }

    /**
     * @param Collection<int, Event> $events
     * @return array<int, array{time:string,title:string,host:string,image:string}>
     */
    private function mapUpcoming(Collection $events, ThemeSetting $theme): array
    {
        return $events
            ->take(3)
            ->map(function (Event $event) use ($theme): array {
                return [
                    'time' => $event->starts_at?->format('D · H:i') ?? 'Próxima emisión',
                    'title' => mb_strtoupper($event->title),
                    'host' => $event->venue ?: $event->location ?: 'Seven Rock Radio',
                    'image' => $this->imageForEvent($event->slug, $theme),
                ];
            })
            ->values()
            ->all();
    }
}
