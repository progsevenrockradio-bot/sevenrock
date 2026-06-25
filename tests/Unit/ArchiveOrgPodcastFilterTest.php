<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\ArchiveOrgService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ArchiveOrgPodcastFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_it_filters_out_podcast_if_broadcast_is_scheduled_today_and_not_finished(): void
    {
        // Monday, May 18th 2026, 10:00:00 UTC
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00', 'UTC'));

        $program = MasterProgram::query()->create([
            'nombre' => 'Morning Rock',
            'conductor' => 'Claudio Wallace',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '11:00',
            'timezone' => 'UTC',
            'duracion_minutos' => 120, // Ends at 13:00 UTC
            'genero' => 'ROCK',
            'activo' => true,
            'archive_identifier' => 'morning-rock-podcast',
        ]);

        // Create an episode that was already uploaded/verified
        RadioProgram::query()->create([
            'titulo_programa' => 'Morning Rock',
            'master_program_id' => $program->id,
            'numero_episodio' => 1,
            'dia_transmision' => 'LUNES',
            'hora_inicio' => '11:00:00',
            'hora_fin' => '13:00:00',
            'conductor' => 'Claudio Wallace',
            'fecha_emision' => '2026-05-18',
            'genero_musical' => 'ROCK',
            'sync_archive_org' => true,
            'archive_org_status' => 'archive_verified',
            'archivo_mp3' => 'morning-rock-01.mp3',
            'archive_org_metadata' => json_encode([
                'identifier' => 'morning-rock-podcast',
                'remote_path' => 'morning-rock-01.mp3',
            ]),
        ]);

        $service = app(ArchiveOrgService::class);

        // At 10:00 UTC, the broadcast has not started (starts at 11:00, ends at 13:00). So it is today and not finished.
        // It must be filtered out!
        $episodes = $service->latestPodcastEpisodes(10);
        $this->assertEmpty($episodes, 'Podcast should be filtered out because it is scheduled today and has not finished.');

        // Move time to 12:00 UTC (during the live broadcast). It must still be filtered out!
        Carbon::setTestNow(Carbon::parse('2026-05-18 12:00:00', 'UTC'));
        $episodes = $service->latestPodcastEpisodes(10);
        $this->assertEmpty($episodes, 'Podcast should be filtered out during the live broadcast.');

        // Move time to 13:01 UTC (after the live broadcast has finished). It must now be visible!
        Carbon::setTestNow(Carbon::parse('2026-05-18 13:01:00', 'UTC'));
        $episodes = $service->latestPodcastEpisodes(10);
        $this->assertNotEmpty($episodes, 'Podcast should be visible after the live broadcast is finished.');
        $this->assertSame('1', $episodes[0]['id'] ?? null);
        $this->assertSame('https://archive.org/details/morning-rock-podcast', $episodes[0]['archive_url'] ?? null);
    }
}
