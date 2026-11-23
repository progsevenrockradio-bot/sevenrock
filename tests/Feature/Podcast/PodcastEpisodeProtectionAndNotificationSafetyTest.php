<?php

declare(strict_types=1);

namespace Tests\Feature\Podcast;

use App\Console\Commands\ReconcilePodcastPipelineCommand;
use App\Events\PodcastArchiveUploaded;
use App\Events\PodcastDeliveryVerified;
use App\Events\PodcastRadiobossUploaded;
use App\Jobs\UploadArchiveOrgJob;
use App\Jobs\UploadRadiobossJob;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PodcastEpisodeProtectionAndNotificationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function createMaster(string $name, string $day, string $time): MasterProgram
    {
        return MasterProgram::create([
            'nombre' => $name,
            'conductor' => 'Tester',
            'genero' => 'Rock',
            'pais' => 'Venezuela',
            'dia_transmision' => $day,
            'hora_transmision' => $time,
            'sync_archive_org' => true,
        ]);
    }

    private function createProgram(array $attributes): RadioProgram
    {
        return RadioProgram::create(array_merge([
            'conductor' => 'Tester',
        ], $attributes));
    }

    public function test_is_superseded_by_newer_episode_detects_newer_verified_episode(): void
    {
        $master = $this->createMaster('Tardes Literarias', 'LUNES', '14:00:00');

        $ep211 = $this->createProgram([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Tardes Literarias',
            'numero_episodio' => 211,
            'fecha_emision' => Carbon::parse('2026-11-16'),
            'archivo_mp3' => 'podcast-inbox/211.mp3',
            'archivo_mp3_disk' => 'r2',
            'radioboss_status' => 'radioboss_pending',
            'delivery_status' => 'delivery_pending',
        ]);

        $ep212 = $this->createProgram([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Tardes Literarias',
            'numero_episodio' => 212,
            'fecha_emision' => Carbon::parse('2026-11-23'),
            'archivo_mp3' => 'podcast-inbox/212.mp3',
            'archivo_mp3_disk' => 'r2',
            'radioboss_status' => 'radioboss_verified',
            'delivery_status' => 'delivery_verified',
        ]);

        $job = new UploadRadiobossJob($ep211->id);
        $supersededBy = $job->isSupersededByNewerEpisode($ep211);

        $this->assertNotNull($supersededBy);
        $this->assertSame($ep212->id, $supersededBy->id);

        // A newer episode is NOT superseded by an older episode
        $job2 = new UploadRadiobossJob($ep212->id);
        $this->assertNull($job2->isSupersededByNewerEpisode($ep212));
    }

    public function test_extract_episode_identity_parses_filename_correctly(): void
    {
        $info1 = UploadRadiobossJob::extractEpisodeIdentity('211.- TARDES LITERARIAS. 16-11-2026.mp3');
        $this->assertSame(211, $info1['episode_number']);
        $this->assertSame('2026-11-16', $info1['broadcast_date']);

        $info2 = UploadRadiobossJob::extractEpisodeIdentity('212.- TARDES LITERARIAS. 23-11-2026.mp3');
        $this->assertSame(212, $info2['episode_number']);
        $this->assertSame('2026-11-23', $info2['broadcast_date']);
    }

    public function test_reconcile_pipeline_recognizes_superseded_episodes_and_does_not_dispatch_radioboss_job(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        Queue::fake([UploadRadiobossJob::class, UploadArchiveOrgJob::class]);
        Event::fake([PodcastDeliveryVerified::class]);

        $master = $this->createMaster('Tardes Literarias', 'LUNES', '14:00:00');

        $ep212 = $this->createProgram([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Tardes Literarias',
            'numero_episodio' => 212,
            'fecha_emision' => Carbon::parse('2026-11-23'),
            'archivo_mp3' => 'programas_procesados/212.- TARDES LITERARIAS. 23-11-2026.mp3',
            'archivo_mp3_disk' => 'r2',
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
            'radioboss_notification_sent_at' => now(),
            'archive_notification_sent_at' => now(),
        ]);

        $ep211 = $this->createProgram([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Tardes Literarias',
            'numero_episodio' => 211,
            'fecha_emision' => Carbon::parse('2026-11-16'),
            'archivo_mp3' => 'programas_procesados/211.- TARDES LITERARIAS. 16-11-2026.mp3',
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now()->subHours(3),
            'processing_finished_at' => now()->subHours(2),
            'editorial_status' => 'approved',
            'radioboss_status' => 'radioboss_pending',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_pending',
        ]);
        $ep211->timestamps = false;
        $ep211->updated_at = now()->subHours(2);
        $ep211->save();

        $this->artisan('podcast:reconcile-pipeline', ['--stale-minutes' => 60])
            ->assertSuccessful();

        // Radioboss upload should NOT be dispatched for ep 211 because it's superseded
        Queue::assertNotPushed(UploadRadiobossJob::class, function ($job) use ($ep211) {
            return $job->radioProgramId === $ep211->id;
        });

        // Ep 211 should be marked as verified/conserved in backup
        $ep211->refresh();
        $this->assertSame('radioboss_verified', $ep211->radioboss_status);
        $this->assertSame($ep212->id, data_get($ep211->delivery_metadata, 'superseded_by_id'));
    }

    public function test_reconcile_pipeline_finalizes_episodes_stuck_in_delivery_partial(): void
    {
        Event::fake([PodcastDeliveryVerified::class]);

        $master = $this->createMaster('Abriendo Cabezas', 'LUNES', '17:00:00');

        $ep47 = $this->createProgram([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Abriendo Cabezas',
            'numero_episodio' => 47,
            'fecha_emision' => Carbon::parse('2026-11-23'),
            'archivo_mp3' => 'programas_procesados/047.- ABRIENDO CABEZAS. 23-11-2026.mp3',
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now()->subHours(3),
            'processing_finished_at' => now()->subHours(2),
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'radioboss_notification_sent_at' => now()->subHours(2),
            'archive_notification_sent_at' => now()->subHours(2),
            'delivery_status' => 'delivery_partial',
        ]);
        $ep47->timestamps = false;
        $ep47->updated_at = now()->subHours(2);
        $ep47->save();

        $this->artisan('podcast:reconcile-pipeline', ['--stale-minutes' => 60])
            ->assertSuccessful();

        Event::assertDispatched(PodcastDeliveryVerified::class, function ($event) use ($ep47) {
            return $event->radioProgramId === $ep47->id;
        });
    }

    public function test_selective_retry_notifications_does_not_reupload_audio(): void
    {
        Queue::fake();
        Event::fake([PodcastRadiobossUploaded::class, PodcastArchiveUploaded::class]);

        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = $this->createMaster('Abriendo Cabezas', 'LUNES', '17:00:00');

        $ep = $this->createProgram([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Abriendo Cabezas',
            'numero_episodio' => 47,
            'fecha_emision' => Carbon::parse('2026-11-23'),
            'archivo_mp3' => 'processed/podcast-audio/47.mp3',
            'archivo_mp3_disk' => 'r2',
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'radioboss_notification_sent_at' => now(), // already accepted by SMTP
            'archive_notification_sent_at' => null,   // pending
            'delivery_status' => 'delivery_partial',
        ]);

        $response = $this->actingAs($user)->post(route('admin.podcast-uploads.retry', $ep), [
            'target' => 'notifications',
        ]);

        $response->assertRedirect();
        Queue::assertNothingPushed();

        // Only Archive notification event should be dispatched, not RadioBOSS
        Event::assertNotDispatched(PodcastRadiobossUploaded::class);
        Event::assertDispatched(PodcastArchiveUploaded::class, function ($event) use ($ep) {
            return $event->radioProgramId === $ep->id;
        });
    }

    public function test_protection_window_calculates_broadcast_period_with_buffer(): void
    {
        $master = $this->createMaster('Tardes Literarias', 'LUNES', '14:00:00');

        $ep = $this->createProgram([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Tardes Literarias',
            'numero_episodio' => 212,
            'fecha_emision' => Carbon::parse('2026-11-23'),
            'archivo_mp3' => 'programas_procesados/212.- TARDES LITERARIAS. 23-11-2026.mp3',
        ]);

        $job = new UploadRadiobossJob($ep->id);

        // Monday at 14:30 Caracas time (inside 14:00 - 16:15 Caracas window)
        Carbon::setTestNow(Carbon::parse('2026-11-23 14:30:00', 'America/Caracas'));

        $window = $job->isInsideLiveBroadcastProtectionWindow($ep);
        $this->assertNotNull($window);
        $this->assertSame('inside_window', $window['status']);
        $this->assertGreaterThan(0, $window['remaining_seconds']);

        // Monday at 16:20 Caracas time (outside window)
        Carbon::setTestNow(Carbon::parse('2026-11-23 16:20:00', 'America/Caracas'));

        $windowOutside = $job->isInsideLiveBroadcastProtectionWindow($ep);
        $this->assertNull($windowOutside);

        Carbon::setTestNow();
    }
}
