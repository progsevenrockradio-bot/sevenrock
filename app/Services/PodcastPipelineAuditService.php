<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RadioProgram;
use App\Models\RadioProgramEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class PodcastPipelineAuditService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function record(RadioProgram $program, string $eventType, string $message = '', array $metadata = []): ?RadioProgramEvent
    {
        if (! Schema::hasTable('radio_program_events')) {
            return null;
        }

        return RadioProgramEvent::query()->create([
            'radio_program_id' => $program->id,
            'event_type' => $eventType,
            'event_message' => trim($message) !== '' ? $message : null,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSnapshot(): array
    {
        if (! Schema::hasTable('radio_programs')) {
            return [
                'counts' => [],
                'recent_events' => [],
                'recent_programs' => [],
            ];
        }

        $counts = [
            'processing' => RadioProgram::query()
                ->whereNotNull('processing_started_at')
                ->whereNull('processing_finished_at')
                ->count(),
            'radioboss_pending' => RadioProgram::query()->where('radioboss_status', 'radioboss_pending')->count(),
            'archive_pending' => RadioProgram::query()->whereIn('archive_org_status', ['archive_pending', 'archive_pending_indexing'])->count(),
            'delivery_partial' => RadioProgram::query()->where('delivery_status', 'delivery_partial')->count(),
            'delivery_failed' => RadioProgram::query()->where('delivery_status', 'delivery_failed')->count(),
            'delivery_verified' => RadioProgram::query()->where('delivery_status', 'delivery_verified')->count(),
        ];

        $recentEvents = Schema::hasTable('radio_program_events')
            ? RadioProgramEvent::query()
                ->with('radioProgram')
                ->latest('created_at')
                ->limit(20)
                ->get()
            : collect();

        $recentPrograms = RadioProgram::query()
            ->with('masterProgram')
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'counts' => $counts,
            'recent_events' => $recentEvents,
            'recent_programs' => $recentPrograms,
        ];
    }

    /**
     * @return array<int, RadioProgramEvent>
     */
    public function timelineFor(RadioProgram $program): array
    {
        if (! Schema::hasTable('radio_program_events')) {
            return [];
        }

        return RadioProgramEvent::query()
            ->where('radio_program_id', $program->id)
            ->orderBy('created_at')
            ->get()
            ->all();
    }
}
