<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\ExtractReleaseCoverJob;
use App\Models\EmailLog;
use App\Models\NewRelease;
use App\Models\TrackSubmission;
use App\Models\User;
use App\Services\SubmissionPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Base: SQLite :memory: (phpunit.xml). Correo: transporte "array" (nada sale) o transporte que falla a propósito.
 * Limitación: SQLite ignora lockForUpdate(); la exclusión concurrente real de MySQL no se demuestra aquí.
 */
final class TrackSubmissionPublishTest extends TestCase
{
    use RefreshDatabase;

    private SubmissionPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->publisher = app(SubmissionPublisher::class);
    }

    private function submission(array $attrs = []): TrackSubmission
    {
        return TrackSubmission::create(array_merge([
            'band_name' => 'Los Test',
            'song_title' => 'Canción Uno',
            'contact_email' => 'banda@example.com',
            'social_link' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'file_path' => 'submissions/los-test/uno.mp3',
            'status' => 'approved',
        ], $attrs));
    }

    private function sentMessages(): int
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->count();
    }

    private function useFailingMailer(): void
    {
        Mail::extend('failing', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('SMTP caído (simulado)');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        });
        config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
        app('mail.manager')->purge();
    }

    private function useArrayMailer(): void
    {
        config(['mail.default' => 'array']);
        app('mail.manager')->purge();
    }

    public function test_publish_links_release_sets_first_publication_and_sends_one_notice(): void
    {
        $s = $this->submission();

        $out = $this->publisher->publish($s, true);

        $this->assertSame(SubmissionPublisher::PUBLISHED, $out['result']);
        $this->assertSame('sent', $out['notice']);

        $s->refresh();
        $this->assertSame(1, NewRelease::count());
        $release = NewRelease::first();
        $this->assertSame($release->id, $s->new_release_id);
        $this->assertSame('submission', $release->origin);
        $this->assertNotNull($s->published_at);
        $this->assertTrue($s->published_to_hub);
        $this->assertSame('sent', $s->publication_notice_status);
        $this->assertSame(1, $s->publication_notice_attempts);
        $this->assertSame($s->id, $release->trackSubmission->id);

        // Un único correo (el observer de NewRelease no envía el suyo para origin=submission)
        $this->assertSame(1, $this->sentMessages());
        $this->assertSame(1, EmailLog::where('track_submission_id', $s->id)->where('status', 'sent')->count());
        Queue::assertPushed(ExtractReleaseCoverJob::class, 1);
    }

    public function test_repeated_publication_recovers_release_without_duplicates_or_new_date(): void
    {
        $s = $this->submission();
        $this->publisher->publish($s, true);
        $first = $s->fresh();
        $this->travel(2)->days();

        $again = $this->publisher->publish($s, true);

        $this->assertSame(SubmissionPublisher::ALREADY_PUBLISHED, $again['result']);
        $this->assertSame(1, NewRelease::count());
        $this->assertSame(1, $this->sentMessages());
        $this->assertTrue($first->published_at->equalTo($s->fresh()->published_at));
        $this->assertSame($first->new_release_id, $s->fresh()->new_release_id);
        Queue::assertPushed(ExtractReleaseCoverJob::class, 1);
    }

    public function test_mail_failure_keeps_publication_and_retry_only_resends_notice(): void
    {
        $this->useFailingMailer();
        $s = $this->submission();

        $out = $this->publisher->publish($s, true);

        $this->assertSame(SubmissionPublisher::PUBLISHED, $out['result']);
        $this->assertSame('failed', $out['notice']);
        $s->refresh();
        $this->assertSame(1, NewRelease::count());
        $this->assertNotNull($s->new_release_id);
        $this->assertNotNull($s->published_at);
        $this->assertSame('failed', $s->publication_notice_status);
        $this->assertStringContainsString('SMTP caído', (string) $s->publication_notice_error);
        $this->assertSame(1, EmailLog::where('status', 'failed')->count());
        $publishedAt = $s->published_at;

        // Repetir "publicar" no reintenta solo el correo fallido ni crea otro lanzamiento
        $this->publisher->publish($s, true);
        $this->assertSame(1, NewRelease::count());
        $this->assertSame(1, (int) $s->fresh()->publication_notice_attempts);

        $this->useArrayMailer();
        $this->assertSame('sent', $this->publisher->retryNotice($s));

        $s->refresh();
        $this->assertSame(1, NewRelease::count());
        $this->assertSame(1, $this->sentMessages());
        $this->assertSame('sent', $s->publication_notice_status);
        $this->assertSame(2, $s->publication_notice_attempts);
        $this->assertTrue($publishedAt->equalTo($s->published_at));
        Queue::assertPushed(ExtractReleaseCoverJob::class, 1);

        // Ya enviado: no se puede reintentar ni se reenvía
        $this->assertSame('not_retriable', $this->publisher->retryNotice($s));
        $this->assertSame(1, $this->sentMessages());
    }

    public function test_notice_is_not_resent_when_state_is_unknown_or_pending(): void
    {
        $s = $this->submission();
        $this->publisher->publish($s, true);

        foreach (['unknown', 'pending'] as $state) {
            $s->forceFill(['publication_notice_status' => $state])->save();
            $this->assertSame('not_retriable', $this->publisher->retryNotice($s));
            $this->assertSame($state, $this->publisher->sendNotice($s->fresh()));
        }

        $this->assertSame(1, $this->sentMessages());
    }

    public function test_send_after_confirmed_send_but_log_failure_marks_unknown_and_never_resends(): void
    {
        $s = $this->submission();
        // Simula fallo del historial DESPUÉS de enviar: la tabla no existe.
        $s->forceFill(['new_release_id' => NewRelease::create([
            'title' => 'X', 'slug' => 'x', 'artist_name' => 'Y', 'is_active' => true,
            'released_at' => now(), 'origin' => 'submission', 'show_in_feed' => false,
        ])->id])->save();
        Schema::drop('email_logs');

        $this->assertSame('unknown', $this->publisher->sendNotice($s->fresh()));
        $this->assertSame(1, $this->sentMessages());
        $this->assertSame('unknown', $s->fresh()->publication_notice_status);
        $this->assertSame('not_retriable', $this->publisher->retryNotice($s->fresh()));
        $this->assertSame(1, $this->sentMessages());
    }

    public function test_not_approved_submission_is_not_published(): void
    {
        $s = $this->submission(['status' => 'pending']);

        $out = $this->publisher->publish($s, true);

        $this->assertSame(SubmissionPublisher::NOT_APPROVED, $out['result']);
        $this->assertSame(0, NewRelease::count());
        $this->assertNull($s->fresh()->new_release_id);
        $this->assertSame(0, $this->sentMessages());
    }

    public function test_historical_and_ambiguous_records_are_not_linked_by_title_artist(): void
    {
        NewRelease::create([
            'title' => 'Canción Uno', 'slug' => 'cancion-uno', 'artist_name' => 'Los Test',
            'is_active' => true, 'released_at' => now(), 'origin' => 'email', 'show_in_feed' => true,
        ]);
        $coincidence = $this->submission();
        $historic = $this->submission(['song_title' => 'Otra', 'published_to_hub' => true]);

        $a = $this->publisher->publish($coincidence, true);
        $b = $this->publisher->publish($historic, true);

        $this->assertSame(SubmissionPublisher::NEEDS_REVIEW, $a['result']);
        $this->assertSame(SubmissionPublisher::NEEDS_REVIEW, $b['result']);
        $this->assertNull($coincidence->fresh()->new_release_id);
        $this->assertNull($historic->fresh()->new_release_id);
        $this->assertNull($coincidence->fresh()->published_at);
        $this->assertSame(1, NewRelease::count());
        $this->assertSame(0, $this->sentMessages());
    }

    public function test_no_notice_when_linked_release_is_not_publicly_accessible(): void
    {
        $cases = [
            'inactivo' => ['is_active' => false],
            'fecha futura' => ['released_at' => now()->addDays(3)],
            'embargo vigente' => ['is_embargo_active' => true, 'embargo_until' => now()->addDay()],
            'embargo sin fecha' => ['is_embargo_active' => true, 'embargo_until' => null],
        ];

        foreach ($cases as $label => $override) {
            $release = NewRelease::create(array_merge([
                'title' => "T $label", 'slug' => 'slug-' . md5($label), 'artist_name' => 'A',
                'is_active' => true, 'released_at' => now(), 'origin' => 'submission', 'show_in_feed' => true,
            ], $override));
            $s = $this->submission(['song_title' => "T $label", 'new_release_id' => $release->id]);

            $this->assertSame('not_public', $this->publisher->sendNotice($s), $label);
            $out = $this->publisher->publish($s, true);
            $this->assertNull($s->fresh()->published_at, $label);
            $this->assertNull($s->fresh()->publication_notice_status, $label);
            $this->assertSame(SubmissionPublisher::ALREADY_PUBLISHED, $out['result'], $label);
        }

        $this->assertSame(0, $this->sentMessages());
    }

    public function test_first_publication_is_registered_once_release_becomes_accessible(): void
    {
        $release = NewRelease::create([
            'title' => 'T', 'slug' => 't', 'artist_name' => 'A', 'is_active' => true,
            'released_at' => now()->addDays(2), 'origin' => 'submission', 'show_in_feed' => true,
        ]);
        $s = $this->submission(['new_release_id' => $release->id]);

        $this->publisher->publish($s, true);
        $this->assertNull($s->fresh()->published_at);

        $this->travel(3)->days();
        $out = $this->publisher->publish($s, true);

        $this->assertNotNull($s->fresh()->published_at);
        $this->assertSame('sent', $out['notice']);
        $this->assertSame(1, $this->sentMessages());
    }

    public function test_manual_origin_keeps_its_own_notification_behaviour(): void
    {
        NewRelease::create([
            'title' => 'Manual', 'slug' => 'manual', 'artist_name' => 'Artista', 'is_active' => true,
            'released_at' => now(), 'origin' => 'manual', 'show_in_feed' => false,
            'author_email' => 'autor@example.com',
        ]);

        $this->assertSame(1, $this->sentMessages());
    }

    public function test_public_page_uses_real_visibility_criteria(): void
    {
        $make = fn (string $slug, array $o = []) => NewRelease::create(array_merge([
            'title' => $slug, 'slug' => $slug, 'artist_name' => 'A', 'is_active' => true,
            'released_at' => now()->subDay(), 'origin' => 'submission', 'show_in_feed' => true,
        ], $o));

        $make('visible');
        $make('sin-portada', ['show_in_feed' => false]);
        $make('inactivo', ['is_active' => false]);
        $make('futuro', ['released_at' => now()->addDays(2)]);
        $make('embargo', ['is_embargo_active' => true, 'embargo_until' => now()->addDay()]);
        $make('embargo-vencido', ['is_embargo_active' => true, 'embargo_until' => now()->subHour()]);

        foreach (['visible', 'sin-portada', 'embargo-vencido'] as $slug) {
            $this->get(route('new-releases.single', $slug))->assertOk();
        }
        foreach (['inactivo', 'futuro', 'embargo'] as $slug) {
            $this->get(route('new-releases.single', $slug))->assertNotFound();
        }

        // Retirar la publicación cierra la ficha
        NewRelease::where('slug', 'visible')->update(['is_active' => false]);
        $this->get(route('new-releases.single', 'visible'))->assertNotFound();
    }

    public function test_admin_individual_and_bulk_publish_share_the_same_logic(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $one = $this->submission(['song_title' => 'Individual']);

        $this->actingAs($admin)->post(route('admin.submissions.publish', $one), ['show_in_feed' => 1])
            ->assertSessionHas('success');
        $this->assertNotNull($one->fresh()->new_release_id);

        $b1 = $this->submission(['song_title' => 'Lote A', 'contact_email' => 'a@example.com']);
        $b2 = $this->submission(['song_title' => 'Lote B', 'contact_email' => 'b@example.com']);
        $pending = $this->submission(['song_title' => 'Pendiente', 'status' => 'pending']);

        $resp = $this->actingAs($admin)->post(route('admin.submissions.bulkPublish'), [
            'action' => 'publish_hidden',
            'submissions' => [$b1->id, $b2->id, $pending->id, $one->id],
        ]);

        $resp->assertSessionHas('success', 'Se publicaron 2 maquetas al Hub correctamente.');
        $this->assertSame(3, NewRelease::count());
        $this->assertSame(3, $this->sentMessages());
        $this->assertFalse((bool) NewRelease::find($b1->fresh()->new_release_id)->show_in_feed);
        $this->assertNull($pending->fresh()->new_release_id);

        // Repetir el lote no duplica nada
        $this->actingAs($admin)->post(route('admin.submissions.bulkPublish'), [
            'action' => 'publish_hidden', 'submissions' => [$b1->id, $b2->id],
        ])->assertSessionHas('error');
        $this->assertSame(3, NewRelease::count());
        $this->assertSame(3, $this->sentMessages());
    }

    public function test_admin_retry_route_only_resends_failed_notice(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->useFailingMailer();
        $s = $this->submission();
        $this->actingAs($admin)->post(route('admin.submissions.publish', $s), [])
            ->assertSessionHas('error');
        $this->assertSame('failed', $s->fresh()->publication_notice_status);

        $this->useArrayMailer();
        $this->actingAs($admin)->post(route('admin.submissions.retryNotice', $s))
            ->assertSessionHas('success');

        $this->assertSame(1, NewRelease::count());
        $this->assertSame(1, $this->sentMessages());
        $this->actingAs($admin)->post(route('admin.submissions.retryNotice', $s))->assertSessionHas('error');
        $this->assertSame(1, $this->sentMessages());
    }

    public function test_emails_link_to_real_public_route_and_make_no_radio_or_account_promises(): void
    {
        $s = $this->submission();
        $this->publisher->publish($s, true);
        $release = $s->fresh()->release;

        $body = EmailLog::where('status', 'sent')->firstOrFail()->body;
        $this->assertSame(1, substr_count($body, 'href="' . $release->url . '"'));
        $this->assertStringContainsString('Equipo de Seven Rock Radio', $body);
        $this->assertStringContainsString('No supone que la canción vaya a sonar en nuestra emisión de radio', $body);
        foreach (['al aire', 'panel', 'tu cuenta', 'programación'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, mb_strtolower($body));
        }

        $approved = (new \App\Mail\SubmissionStatusUpdated($s->fresh()))->render();
        $this->assertStringContainsString('no implica por sí misma su publicación', $approved);
        $this->assertStringNotContainsString('programación', $approved);
        $this->assertStringNotContainsString('formará parte', $approved);
    }

    public function test_new_receipt_is_sent_and_recorded_in_email_history(): void
    {
        Storage::fake('r2');

        $this->post(route('submissions.store'), [
            'band_name' => 'Banda Nueva',
            'song_title' => 'Tema Nuevo',
            'contact_email' => 'nueva@example.com',
            'audio_file' => UploadedFile::fake()->create('tema.mp3', 100, 'audio/mpeg'),
        ])->assertSessionHasNoErrors();

        $s = TrackSubmission::where('band_name', 'Banda Nueva')->firstOrFail();
        $this->assertSame(1, $this->sentMessages());
        $this->assertSame(1, EmailLog::where('track_submission_id', $s->id)->where('status', 'sent')->count());
        // El acuse no activa ningún aviso de publicación
        $this->assertNull($s->publication_notice_status);
    }

    public function test_failed_receipt_keeps_the_submission_and_records_the_failure(): void
    {
        Storage::fake('r2');
        $this->useFailingMailer();

        $this->post(route('submissions.store'), [
            'band_name' => 'Banda Sin Correo',
            'song_title' => 'Tema Sin Acuse',
            'contact_email' => 'sincorreo@example.com',
            'audio_file' => UploadedFile::fake()->create('tema.mp3', 100, 'audio/mpeg'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $s = TrackSubmission::where('band_name', 'Banda Sin Correo')->firstOrFail();
        $this->assertSame('pending', $s->status);
        $log = EmailLog::where('track_submission_id', $s->id)->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('SMTP caído', (string) $log->body);
        $this->assertNull($s->publication_notice_status);
    }

    public function test_rejection_email_subject_is_neutral(): void
    {
        $s = $this->submission(['status' => 'rejected']);

        $this->assertSame('Resultado de la revisión de tu maqueta', (new \App\Mail\SubmissionStatusUpdated($s))->envelope()->subject);
        $this->assertSame(
            'Tu maqueta ha sido aprobada - Seven Rock Radio',
            (new \App\Mail\SubmissionStatusUpdated($this->submission(['song_title' => 'Otra'])))->envelope()->subject
        );
    }

    public function test_migration_down_removes_foreign_key_and_columns_and_can_be_reapplied(): void
    {
        $cols = ['new_release_id', 'published_at', 'publication_notice_status', 'publication_notice_attempts', 'publication_notice_sent_at', 'publication_notice_error'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('track_submissions', $c), $c);
        }

        $path = 'database/migrations/2026_11_04_212028_add_release_tracking_to_track_submissions_table.php';
        Artisan::call('migrate:rollback', ['--path' => $path, '--force' => true]);
        foreach ($cols as $c) {
            $this->assertFalse(Schema::hasColumn('track_submissions', $c), "no eliminada: $c");
        }

        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        $this->assertTrue(Schema::hasColumn('track_submissions', 'new_release_id'));
    }
}
