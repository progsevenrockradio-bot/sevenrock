<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\SubmissionMuroInvitationMail;
use App\Models\EmailLog;
use App\Models\MarketingContact;
use App\Models\NewRelease;
use App\Models\SubmissionMuroFollowup;
use App\Models\Talent;
use App\Models\TrackSubmission;
use App\Models\User;
use App\Services\SubmissionFollowupService;
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

final class TrackSubmissionMuroFollowupTest extends TestCase
{
    use RefreshDatabase;

    private SubmissionPublisher $publisher;
    private SubmissionFollowupService $followups;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->publisher = app(SubmissionPublisher::class);
        $this->followups = app(SubmissionFollowupService::class);
    }

    private function createSubmission(array $attrs = []): TrackSubmission
    {
        return TrackSubmission::create(array_merge([
            'band_name' => 'Banda Seguimiento',
            'song_title' => 'Riff de Fuego',
            'contact_email' => 'banda@seguimiento.test',
            'social_link' => 'https://instagram.com/bandaseguimiento',
            'file_path' => 'submissions/banda/riff.mp3',
            'status' => 'approved',
            'marketing_opt_in' => true,
            'marketing_opt_in_at' => now(),
            'marketing_opt_in_source' => TrackSubmission::CONSENT_SOURCE_FORM,
            'marketing_opt_in_version' => TrackSubmission::CONSENT_TEXT_VERSION,
        ], $attrs));
    }

    private function enableApprovedCampaign(): void
    {
        config([
            'muro_outreach.enabled' => true,
            'muro_outreach.cta_url' => '/muro-del-rock/plan-basico',
        ]);
    }

    private function sentMessagesCount(): int
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->count();
    }

    private function resetSentMessages(): void
    {
        Mail::mailer()->getSymfonyTransport()->flush();
    }

    private function useFailingMailer(): void
    {
        Mail::extend('failing_muro', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('SMTP caído en invitación Muro (simulado)');
            }

            public function __toString(): string
            {
                return 'failing_muro';
            }
        });
        config(['mail.mailers.failing_muro' => ['transport' => 'failing_muro'], 'mail.default' => 'failing_muro']);
        app('mail.manager')->purge();
    }

    private function useArrayMailer(): void
    {
        config(['mail.default' => 'array']);
        app('mail.manager')->purge();
    }

    public function test_submission_form_shows_unchecked_optional_consent_and_stores_metadata_accurately(): void
    {
        Storage::fake('r2');

        $formHtml = $this->get(route('submissions.create'))->assertOk()->getContent();
        $this->assertStringContainsString('name="marketing_opt_in"', (string) $formHtml);
        $this->assertStringContainsString(TrackSubmission::CONSENT_TEXT, (string) $formHtml);
        $this->assertDoesNotMatchRegularExpression('/name="marketing_opt_in"[^>]*required/i', (string) $formHtml);

        // 1. Envío SIN marcar la casilla opcional
        $this->post(route('submissions.store'), [
            'band_name' => 'Banda Sin OptIn',
            'song_title' => 'Tema Libre',
            'contact_email' => 'sinoptin@example.com',
            'audio_file' => UploadedFile::fake()->create('libre.mp3', 100, 'audio/mpeg'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $withoutOptIn = TrackSubmission::where('contact_email', 'sinoptin@example.com')->firstOrFail();
        $this->assertFalse($withoutOptIn->marketing_opt_in);
        $this->assertNull($withoutOptIn->marketing_opt_in_at);
        $this->assertSame(TrackSubmission::CONSENT_SOURCE_FORM, $withoutOptIn->marketing_opt_in_source);
        $this->assertSame(TrackSubmission::CONSENT_TEXT_VERSION, $withoutOptIn->marketing_opt_in_version);
        $this->assertFalse($withoutOptIn->hasValidMarketingConsent());

        // 2. Envío MARCANDO la casilla opcional
        $this->post(route('submissions.store'), [
            'band_name' => 'Banda Con OptIn',
            'song_title' => 'Tema Con Permiso',
            'contact_email' => 'conoptin@example.com',
            'audio_file' => UploadedFile::fake()->create('permiso.mp3', 100, 'audio/mpeg'),
            'marketing_opt_in' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $withOptIn = TrackSubmission::where('contact_email', 'conoptin@example.com')->firstOrFail();
        $this->assertTrue($withOptIn->marketing_opt_in);
        $this->assertNotNull($withOptIn->marketing_opt_in_at);
        $this->assertSame(TrackSubmission::CONSENT_SOURCE_FORM, $withOptIn->marketing_opt_in_source);
        $this->assertSame(TrackSubmission::CONSENT_TEXT_VERSION, $withOptIn->marketing_opt_in_version);
        $this->assertTrue($withOptIn->hasValidMarketingConsent());
        $this->assertDatabaseHas('marketing_contacts', [
            'email' => 'conoptin@example.com',
            'is_active' => 1,
        ]);
    }

    public function test_historic_contacts_and_non_consented_submissions_are_excluded_and_never_emailed(): void
    {
        $this->enableApprovedCampaign();

        // Contacto histórico (sin fuente ni versión de consentimiento)
        $historic = $this->createSubmission([
            'band_name' => 'Banda Histórica',
            'contact_email' => 'historica@example.com',
            'marketing_opt_in' => false,
            'marketing_opt_in_at' => null,
            'marketing_opt_in_source' => null,
            'marketing_opt_in_version' => null,
        ]);

        // Contacto nuevo que decidió NO marcar la casilla
        $noConsent = $this->createSubmission([
            'band_name' => 'Banda Sin Casilla',
            'song_title' => 'Tema Dos',
            'contact_email' => 'sincasilla@example.com',
            'marketing_opt_in' => false,
            'marketing_opt_in_at' => null,
        ]);

        // Ambos pueden publicarse con total normalidad en el Hub
        $this->publisher->publish($historic, true);
        $this->publisher->publish($noConsent, true);
        $this->resetSentMessages();

        $fHistoric = SubmissionMuroFollowup::where('email_normalized', 'historica@example.com')->firstOrFail();
        $fNoConsent = SubmissionMuroFollowup::where('email_normalized', 'sincasilla@example.com')->firstOrFail();

        $this->assertSame(SubmissionMuroFollowup::STATUS_SKIPPED, $fHistoric->status);
        $this->assertSame(SubmissionMuroFollowup::REASON_HISTORIC_NO_CONSENT, $fHistoric->status_reason);

        $this->assertSame(SubmissionMuroFollowup::STATUS_SKIPPED, $fNoConsent->status);
        $this->assertSame(SubmissionMuroFollowup::REASON_MISSING_CONSENT, $fNoConsent->status_reason);

        // Aunque pasen 4 días, no se envía ninguna invitación comercial
        $this->travel(4)->days();
        $summary = $this->followups->processDueFollowups(false);
        $this->assertSame(0, $summary['sent']);
        $this->assertSame(0, $this->sentMessagesCount());
    }

    public function test_three_day_schedule_and_safety_blocks_before_activation_and_cta_url(): void
    {
        $s = $this->createSubmission();
        $this->publisher->publish($s, true);
        $this->resetSentMessages();

        $followup = SubmissionMuroFollowup::firstOrFail();
        $this->assertSame(SubmissionMuroFollowup::STATUS_SCHEDULED, $followup->status);
        $this->assertSame(
            $s->fresh()->published_at->copy()->addDays(3)->toDateTimeString(),
            $followup->scheduled_for->toDateTimeString()
        );

        // 1. A los 2 días: aún no vence el plazo de 3 días
        $this->travel(2)->days();
        $this->enableApprovedCampaign();
        $resEarly = $this->followups->processDueFollowups(false);
        $this->assertSame(0, $resEarly['sent']);
        $this->assertSame(1, $resEarly['blocked_or_waiting']);
        $this->assertSame(0, $this->sentMessagesCount());

        // 2. A los 3 días y 1 hora, pero con URL apuntando a /talents/register (bloqueada por cuotas contradictorias)
        $this->travel(25)->hours();
        config([
            'muro_outreach.enabled' => true,
            'muro_outreach.cta_url' => 'https://sevenrockradio.com/talents/register',
        ]);
        $resBadUrl = $this->followups->processDueFollowups(false);
        $this->assertSame(0, $resBadUrl['sent']);
        $this->assertSame(0, $this->sentMessagesCount());
        $this->assertSame(SubmissionMuroFollowup::STATUS_SCHEDULED, $followup->fresh()->status);

        // 3. Con URL aprobada, pero campaña desactivada por defecto (enabled = false)
        config([
            'muro_outreach.enabled' => false,
            'muro_outreach.cta_url' => '/muro-del-rock/plan-basico',
        ]);
        $resDisabled = $this->followups->processDueFollowups(false);
        $this->assertSame(0, $resDisabled['sent']);
        $this->assertSame(0, $this->sentMessagesCount());

        // 4. Con campaña activada y URL aprobada: envía exactamente 1 correo y no repite
        $this->enableApprovedCampaign();
        $resOk = $this->followups->processDueFollowups(false);
        $this->assertSame(1, $resOk['sent']);
        $this->assertSame(1, $this->sentMessagesCount());
        $this->assertSame(SubmissionMuroFollowup::STATUS_SENT, $followup->fresh()->status);
        $this->assertNotNull($followup->fresh()->sent_at);

        // Segunda ejecución: no vuelve a enviar
        $resRepeat = $this->followups->processDueFollowups(false);
        $this->assertSame(0, $resRepeat['sent']);
        $this->assertSame(1, $this->sentMessagesCount());
    }

    public function test_multiple_submissions_from_same_contact_produce_only_one_followup_and_one_email(): void
    {
        $this->enableApprovedCampaign();

        $s1 = $this->createSubmission([
            'song_title' => 'Canción A',
            'contact_email' => 'Banda@MismoContacto.com ',
        ]);
        $s2 = $this->createSubmission([
            'song_title' => 'Canción B',
            'contact_email' => 'banda@mismocontacto.com',
        ]);
        $s3 = $this->createSubmission([
            'song_title' => 'Canción C',
            'contact_email' => 'BANDA@MISMOCONTACTO.COM',
        ]);

        $this->publisher->publish($s1, true);
        $this->publisher->publish($s2, true);
        $this->publisher->publish($s3, true);
        $this->resetSentMessages();

        $this->assertSame(1, SubmissionMuroFollowup::count());

        $this->travel(4)->days();
        $res = $this->followups->processDueFollowups(false);
        $this->assertSame(1, $res['sent']);
        $this->assertSame(1, $this->sentMessagesCount());

        // Si publica una cuarta canción después de haber recibido la invitación, tampoco crea otra
        $s4 = $this->createSubmission([
            'song_title' => 'Canción D',
            'contact_email' => 'banda@mismocontacto.com',
        ]);
        $this->publisher->publish($s4, true);
        $this->resetSentMessages();

        $this->assertSame(1, SubmissionMuroFollowup::count());
        $this->travel(4)->days();
        $this->followups->processDueFollowups(false);
        $this->assertSame(0, $this->sentMessagesCount());
    }

    public function test_one_click_unsubscribe_without_login_cancels_followup_and_blocks_sending(): void
    {
        $this->enableApprovedCampaign();

        $s = $this->createSubmission(['contact_email' => 'baja@example.com']);
        $this->publisher->publish($s, true);
        $this->resetSentMessages();

        $token = MarketingContact::getUnsubscribeTokenForEmail('baja@example.com');
        $signedUrl = \Illuminate\Support\Facades\URL::signedRoute('marketing.unsubscribe', ['token' => $token]);

        // Un visitante sin sesión abre el enlace de baja
        $this->assertGuest();
        $this->get($signedUrl)->assertOk()->assertSee('Te hemos dado de baja');

        $this->assertTrue(MarketingContact::isEmailUnsubscribed('baja@example.com'));
        $followup = SubmissionMuroFollowup::where('email_normalized', 'baja@example.com')->firstOrFail();
        $this->assertSame(SubmissionMuroFollowup::STATUS_CANCELLED, $followup->status);
        $this->assertSame(SubmissionMuroFollowup::REASON_UNSUBSCRIBED, $followup->status_reason);

        $this->travel(4)->days();
        $this->followups->processDueFollowups(false);
        $this->assertSame(0, $this->sentMessagesCount());
    }

    public function test_withdrawn_or_embargoed_release_is_not_sent_at_day_three(): void
    {
        $this->enableApprovedCampaign();

        $s = $this->createSubmission(['contact_email' => 'retirada@example.com']);
        $this->publisher->publish($s, true);
        $this->resetSentMessages();

        // Al segundo día se retira la publicación (is_active = false)
        NewRelease::where('id', $s->fresh()->new_release_id)->update(['is_active' => false]);

        $this->travel(4)->days();
        $res = $this->followups->processDueFollowups(false);

        $this->assertSame(0, $res['sent']);
        $this->assertSame(1, $res['skipped']);
        $this->assertSame(0, $this->sentMessagesCount());

        $followup = SubmissionMuroFollowup::where('email_normalized', 'retirada@example.com')->firstOrFail();
        $this->assertSame(SubmissionMuroFollowup::STATUS_SKIPPED, $followup->status);
        $this->assertSame(SubmissionMuroFollowup::REASON_RELEASE_WITHDRAWN, $followup->status_reason);
    }

    public function test_band_with_active_paid_plan_is_excluded(): void
    {
        $this->enableApprovedCampaign();

        Talent::factory()->create([
            'band_name' => 'Banda Ya Pagando',
            'email' => 'pagando@example.com',
            'plan' => 'basic',
            'subscription_status' => 'active',
        ]);

        $s = $this->createSubmission([
            'band_name' => 'Banda Ya Pagando',
            'contact_email' => 'pagando@example.com',
        ]);
        $this->publisher->publish($s, true);
        $this->resetSentMessages();

        $followup = SubmissionMuroFollowup::where('email_normalized', 'pagando@example.com')->firstOrFail();
        $this->assertSame(SubmissionMuroFollowup::STATUS_SKIPPED, $followup->status);
        $this->assertSame(SubmissionMuroFollowup::REASON_ACTIVE_PAID_PLAN, $followup->status_reason);

        $this->travel(4)->days();
        $this->followups->processDueFollowups(false);
        $this->assertSame(0, $this->sentMessagesCount());
    }

    public function test_admin_controls_pause_resume_cancel_and_cannot_bypass_consent_or_unsubscribe(): void
    {
        $this->enableApprovedCampaign();
        $admin = User::factory()->create(['is_admin' => true]);

        $s = $this->createSubmission(['contact_email' => 'control@example.com']);
        $this->publisher->publish($s, true);
        $this->resetSentMessages();

        $followup = SubmissionMuroFollowup::firstOrFail();

        // 1. Pausar desde el panel impide el envío al día 4
        $this->actingAs($admin)
            ->post(route('admin.submissions.followups.pause', $followup))
            ->assertSessionHas('success');
        $this->assertSame(SubmissionMuroFollowup::STATUS_PAUSED, $followup->fresh()->status);

        $this->travel(4)->days();
        $this->followups->processDueFollowups(false);
        $this->assertSame(0, $this->sentMessagesCount());

        // 2. Reanudar vuelve a scheduled cuando sigue cumpliendo requisitos
        $this->actingAs($admin)
            ->post(route('admin.submissions.followups.resume', $followup))
            ->assertSessionHas('success');
        $this->assertSame(SubmissionMuroFollowup::STATUS_SCHEDULED, $followup->fresh()->status);

        // 3. Si se pausa y entretanto el contacto se da de baja, el admin NO puede forzar la reanudación
        $this->actingAs($admin)->post(route('admin.submissions.followups.pause', $followup));
        MarketingContact::updateOrCreate(
            ['email' => 'control@example.com'],
            [
                'band_name' => 'Banda Seguimiento',
                'is_active' => false,
                'unsubscribed_at' => now(),
                'unsubscribe_token' => 'token-baja-control',
            ]
        );

        $this->actingAs($admin)
            ->post(route('admin.submissions.followups.resume', $followup))
            ->assertSessionHas('error');
        $this->assertSame(SubmissionMuroFollowup::STATUS_SKIPPED, $followup->fresh()->status);
        $this->assertSame(SubmissionMuroFollowup::REASON_UNSUBSCRIBED, $followup->fresh()->status_reason);

        // 4. Cancelar otro seguimiento programado impide su envío
        $s2 = $this->createSubmission([
            'band_name' => 'Otra Banda',
            'song_title' => 'Otro Tema',
            'contact_email' => 'cancelar@example.com',
        ]);
        $this->publisher->publish($s2, true);
        $this->resetSentMessages();
        $f2 = SubmissionMuroFollowup::where('email_normalized', 'cancelar@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.submissions.followups.cancel', $f2))
            ->assertSessionHas('success');
        $this->assertSame(SubmissionMuroFollowup::STATUS_CANCELLED, $f2->fresh()->status);
    }

    public function test_mail_failure_records_failed_and_allows_retry_while_unknown_never_resends(): void
    {
        $this->enableApprovedCampaign();
        $admin = User::factory()->create(['is_admin' => true]);

        $s = $this->createSubmission(['contact_email' => 'fallo@example.com']);
        $this->publisher->publish($s, true);
        $this->resetSentMessages();

        $followup = SubmissionMuroFollowup::firstOrFail();
        $this->travel(4)->days();

        // 1. Fallo confirmado de SMTP deja estado 'failed' y 1 intento
        $this->useFailingMailer();
        $res = $this->followups->processDueFollowups(false);
        $this->assertSame(1, $res['failed']);
        $followup->refresh();
        $this->assertSame(SubmissionMuroFollowup::STATUS_FAILED, $followup->status);
        $this->assertSame(1, $followup->attempts);
        $this->assertStringContainsString('SMTP caído', (string) $followup->last_error);

        // 2. Restablecido el transporte, el admin reintenta el fallo confirmado y se envía 1 vez
        $this->useArrayMailer();
        $this->actingAs($admin)
            ->post(route('admin.submissions.followups.retry', $followup))
            ->assertSessionHas('success');

        $followup->refresh();
        $this->assertSame(SubmissionMuroFollowup::STATUS_SENT, $followup->status);
        $this->assertSame(2, $followup->attempts);
        $this->assertSame(1, $this->sentMessagesCount());

        // 3. Si otro seguimiento falla al registrar en email_logs DESPUÉS de enviar, queda en 'unknown' y no es reintentable
        $s2 = $this->createSubmission([
            'band_name' => 'Banda Incierto',
            'song_title' => 'Tema Incierto',
            'contact_email' => 'incierto@example.com',
        ]);
        $this->publisher->publish($s2, true);
        $this->resetSentMessages();
        $f2 = SubmissionMuroFollowup::where('email_normalized', 'incierto@example.com')->firstOrFail();

        $this->travel(4)->days();
        Schema::drop('email_logs');

        $this->followups->processDueFollowups(false);
        $this->assertSame(1, $this->sentMessagesCount());
        $this->assertSame(SubmissionMuroFollowup::STATUS_UNKNOWN, $f2->fresh()->status);

        // Intento de reintento administrativo sobre 'unknown' es rechazado
        $retryRes = $this->followups->retryFailed($f2->fresh());
        $this->assertFalse($retryRes['ok']);
        $this->assertSame(1, $this->sentMessagesCount());
    }

    public function test_invitation_email_content_reply_to_and_phase2_migration_rollback(): void
    {
        $this->enableApprovedCampaign();

        $s = $this->createSubmission(['contact_email' => 'contenido@example.com']);
        $this->publisher->publish($s, true);
        $this->resetSentMessages();

        $this->travel(4)->days();
        $this->followups->processDueFollowups(false);

        $log = EmailLog::where('to_email', 'contenido@example.com')
            ->where('subject', 'like', '%Muro del Rock%')
            ->firstOrFail();

        $body = (string) $log->body;
        $this->assertStringContainsString('3,50 €/mes', $body);
        $this->assertStringContainsString('Plan Básico', $body);
        $this->assertStringContainsString('Equipo de Seven Rock Radio', $body);
        $this->assertStringContainsString('Contratar este servicio es opcional y no condiciona la publicación de tu canción en nuestro catálogo.', $body);
        $this->assertStringContainsString('La publicación está sujeta a nuestros criterios editoriales y es independiente de la programación de radio.', $body);
        $this->assertStringNotContainsString('seguirá publicada', $body);
        $this->assertStringContainsString('/baja/', $body);
        $this->assertStringContainsString('/muro-del-rock/plan-basico', $body);
        $this->assertStringNotContainsString('/talents/register', $body);
        $this->assertStringNotContainsString('/talentos/register', $body);

        // El destino comercial del correo responde 200 OK
        $this->get('/muro-del-rock/plan-basico')->assertOk();

        // No promete emisión ni cuotas pendientes de unificar
        foreach (['5 canciones', '10 fotos', '200 mb', 'rotación de radio', 'sonar en nuestra'] as $forbidden) {
            // Verifica que no prometa cuotas ni rotación
            if ($forbidden !== 'sonar en nuestra') {
                $this->assertStringNotContainsString($forbidden, mb_strtolower($body));
            }
        }

        $this->assertSame('prog.sevenrockradio@gmail.com', SubmissionMuroInvitationMail::configuredReplyToAddress());

        // Comprobar reversión limpia de la migración de Fase 2
        $path = 'database/migrations/2026_11_04_221500_add_consent_and_muro_followups_tables.php';
        Artisan::call('migrate:rollback', ['--path' => $path, '--force' => true]);
        $this->assertFalse(Schema::hasTable('submission_muro_followups'));
        $this->assertFalse(Schema::hasColumn('track_submissions', 'marketing_opt_in'));

        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        $this->assertTrue(Schema::hasTable('submission_muro_followups'));
        $this->assertTrue(Schema::hasColumn('track_submissions', 'marketing_opt_in'));
    }
}
