<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewRelease;
use App\Models\ReleaseIntakeSender;
use App\Models\ReleaseIntakeSetting;
use App\Models\User;
use App\Services\ReleaseIntake\ReleaseEmailIntakeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class ReleaseIntakeAndReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-view-cache';
        if (! is_dir($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }

        config([
            'view.compiled' => $compiledPath,
            'app.timezone' => 'America/Caracas',
        ]);
    }

    public function test_intake_starts_disabled_and_registers_proposal_without_publishing_or_downloading_audio_and_deduplicates(): void
    {
        Bus::fake();
        Mail::fake();

        $intake = app(ReleaseEmailIntakeService::class);

        // 1. Inicialmente desactivada hasta configurar piloto
        $disabledResult = $intake->poll();
        $this->assertSame('disabled', $disabledResult['status']);

        $sender = ReleaseIntakeSender::query()->create([
            'email' => 'prensa@ripplesello.test',
            'entity_name' => 'Ripple Music',
            'entity_type' => 'label',
            'is_active' => true,
        ]);

        $payload = [
            'message_id' => '<promo-2026-001@ripplesello.test>',
            'sender_email' => 'prensa@ripplesello.test',
            'subject' => 'Bright Curse - Fatum (Nuevo álbum)',
            'body_text' => 'El nuevo álbum saldrá a la venta el 15 de enero de 2027. Enlace oficial: https://brightcurse.bandcamp.com/album/fatum Descarga prensa: https://drive.google.com/file/d/abc123',
            'raw_headers' => "Authentication-Results: mx.google.com; spf=pass; dkim=pass header.d=ripplesello.test; dmarc=pass\r\n",
            'received_at' => Carbon::create(2026, 11, 28, 10, 0, 0, 'America/Caracas'),
            'folder' => 'INBOX',
            'mailbox' => 'piloto@sevenrockradio.test',
            'attachments' => ['01-the-fates.mp3', 'presskit.zip'],
        ];

        // 2. Primera consulta: registra propuesta en Por revisar sin publicar ni descargar audio
        $first = $intake->ingestAuthorizedMessage($payload, $sender);
        $this->assertSame('registered', $first['outcome']);
        $release = $first['release'];
        $this->assertInstanceOf(NewRelease::class, $release);
        $this->assertFalse($release->is_active);
        $this->assertSame(NewRelease::REVIEW_PENDING, $release->review_status);
        $this->assertSame('album', $release->release_type);
        $this->assertSame('2026-11-28', $release->announced_at?->toDateString());
        $this->assertSame('2027-01-15', $release->released_at?->toDateString());
        $this->assertNull($release->audio_path);
        $this->assertNull($release->description); // Propuesta no se convierte automáticamente en reseña verificada
        $this->assertNotEmpty($release->proposal_summary);
        $this->assertFalse((bool) data_get($release->intake_metadata, 'attachments_downloaded'));
        $this->assertFalse((bool) data_get($release->intake_metadata, 'used_external_ai'));
        $this->assertSame('verified', data_get($release->source_sender_auth, 'status'));

        // 3. Segunda consulta con el mismo Message-ID: deduplica sin duplicar ni sobrescribir
        $second = $intake->ingestAuthorizedMessage($payload, $sender);
        $this->assertSame('duplicate', $second['outcome']);
        $this->assertSame(1, NewRelease::query()->where('source_message_id', 'promo-2026-001@ripplesello.test')->count());

        // 4. Relación de posibles duplicados: single del mismo nombre se relaciona sin fusionar automáticamente
        $single = NewRelease::withoutEvents(static fn () => NewRelease::query()->create([
            'title' => 'Fatum (Nuevo álbum)',
            'slug' => 'fatum-single',
            'artist_name' => 'Bright Curse',
            'release_type' => 'single',
            'is_active' => false,
            'review_status' => NewRelease::REVIEW_PENDING,
            'origin' => 'manual',
        ]));

        $dups = $single->findPotentialDuplicates();
        $this->assertCount(1, $dups);
        $this->assertTrue($dups->first()['different_type']);

        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
    }

    public function test_approval_rejection_and_return_to_review_respect_embargo_and_preserve_records(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Editor Jefe']);

        // 1. Propuesta bajo embargo activo -> aprobación bloqueada
        $embargoed = NewRelease::withoutEvents(static fn () => NewRelease::query()->create([
            'title' => 'Disco Embargado',
            'slug' => 'disco-embargado',
            'artist_name' => 'Banda Embargo',
            'release_type' => 'album',
            'is_active' => false,
            'review_status' => NewRelease::REVIEW_PENDING,
            'security_status' => 'needs_review',
            'is_embargo_active' => true,
            'embargo_until' => now()->addDays(7),
            'origin' => 'email',
        ]));

        $this->actingAs($admin)
            ->post(route('admin.new-releases.approve', $embargoed))
            ->assertSessionHasErrors('is_active');

        $this->assertFalse($embargoed->fresh()->is_active);
        $this->assertSame(NewRelease::REVIEW_PENDING, $embargoed->fresh()->review_status);

        // 2. Propuesta válida sin embargo -> aprobación manual exitosa
        $valid = NewRelease::withoutEvents(static fn () => NewRelease::query()->create([
            'title' => 'Nuevo Single Autorizado',
            'slug' => 'nuevo-single-autorizado',
            'artist_name' => 'Banda Libre',
            'release_type' => 'single',
            'announced_at' => '2026-11-28',
            'released_at' => '2026-12-10',
            'description' => 'Reseña editorial verificada para difusión.',
            'is_active' => false,
            'review_status' => NewRelease::REVIEW_PENDING,
            'security_status' => 'needs_review',
            'origin' => 'email',
        ]));

        $this->actingAs($admin)
            ->post(route('admin.new-releases.approve', $valid))
            ->assertRedirect(route('admin.new-releases.index', ['tab' => 'published']));

        $valid->refresh();
        $this->assertTrue($valid->is_active);
        $this->assertSame(NewRelease::REVIEW_PUBLISHED, $valid->review_status);
        $this->assertNotNull($valid->published_at);
        $this->assertSame($admin->id, $valid->reviewed_by);

        // 3. Rechazar con motivo conserva el registro (no borra) y permite devolver a revisión
        $this->actingAs($admin)
            ->post(route('admin.new-releases.reject', $embargoed), [
                'rejection_reason' => 'Sin permiso de portada acreditado.',
            ])
            ->assertRedirect(route('admin.new-releases.index', ['tab' => 'rejected']));

        $embargoed->refresh();
        $this->assertSame(NewRelease::REVIEW_REJECTED, $embargoed->review_status);
        $this->assertSame('Sin permiso de portada acreditado.', $embargoed->rejection_reason);
        $this->assertFalse($embargoed->is_active);

        $this->actingAs($admin)
            ->post(route('admin.new-releases.return-to-review', $embargoed))
            ->assertRedirect(route('admin.new-releases.index', ['tab' => 'pending_review']));

        $this->assertSame(NewRelease::REVIEW_PENDING, $embargoed->fresh()->review_status);
    }

    public function test_homepage_displays_future_announcement_properly_without_monopolizing_order(): void
    {
        // Ficha publicada antes anunciando un álbum lejano (2027)
        NewRelease::withoutEvents(static fn () => NewRelease::query()->create([
            'title' => 'Album Futuro 2027',
            'slug' => 'album-futuro-2027',
            'artist_name' => 'Bright Curse',
            'release_type' => 'album',
            'announced_at' => '2026-11-20',
            'released_at' => '2027-01-15',
            'published_at' => Carbon::create(2026, 11, 20, 10, 0, 0, 'America/Caracas'),
            'description' => 'Anuncio oficial de tercer disco.',
            'is_active' => true,
            'show_in_feed' => true,
            'review_status' => NewRelease::REVIEW_PUBLISHED,
            'security_status' => 'passed',
            'origin' => 'manual',
        ]));

        // Ficha publicada más recientemente con lanzamiento ya editado -> debe ir primero en portada
        NewRelease::withoutEvents(static fn () => NewRelease::query()->create([
            'title' => 'Single Reciente Hoy',
            'slug' => 'single-reciente-hoy',
            'artist_name' => 'Banda Actual',
            'release_type' => 'single',
            'announced_at' => '2026-11-28',
            'released_at' => '2026-11-28',
            'published_at' => Carbon::create(2026, 11, 28, 12, 0, 0, 'America/Caracas'),
            'description' => 'Nuevo sencillo recién estrenado.',
            'is_active' => true,
            'show_in_feed' => true,
            'review_status' => NewRelease::REVIEW_PUBLISHED,
            'security_status' => 'passed',
            'origin' => 'manual',
        ]));

        $response = $this->get(route('home'));
        $response->assertOk()
            ->assertSee('Próximo lanzamiento')
            ->assertSee('Anunciado el')
            ->assertSee('Lanzamiento previsto para el')
            ->assertSee('Publicado en SevenRock el')
            ->assertSeeInOrder(['Single Reciente Hoy', 'Album Futuro 2027']);
    }
}

