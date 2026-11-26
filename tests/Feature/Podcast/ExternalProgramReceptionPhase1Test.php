<?php

declare(strict_types=1);

namespace Tests\Feature\Podcast;

use App\Models\ExternalProgramDelivery;
use App\Models\ExternalProgramSetting;
use App\Models\MasterProgram;
use App\Models\MasterProgramDeliverySender;
use App\Models\RadioProgram;
use App\Models\User;
use App\Services\ExternalPrograms\ExternalDeliveryLinkDetector;
use App\Services\ExternalPrograms\ExternalProgramInboxPoller;
use App\Services\ExternalPrograms\ExternalProgramReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;

final class ExternalProgramReceptionPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Europe/Madrid',
            'services.imap.host' => 'imap.gmail.com',
            'services.imap.port' => 993,
            'services.imap.encryption' => 'ssl',
            'services.imap.username' => 'prog.sevenrockradio@gmail.com',
            'services.imap.password' => 'test-app-pass',
            'services.external_programs.mailbox' => 'prog.sevenrockradio@gmail.com',
            'services.external_programs.since_date' => '2026-11-26 00:00:00',
            'services.external_programs.telegram_bot_token' => '123456:TEST-BOT-TOKEN',
            'services.external_programs.telegram_chat_id' => '999888777',
        ]);
    }

    private function createMasterProgram(array $overrides = []): MasterProgram
    {
        return MasterProgram::query()->create(array_merge([
            'nombre' => 'Rock Sin Fronteras',
            'conductor' => 'Carlos Rock',
            'genero' => 'Hard Rock',
            'pais' => 'España',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '20:00:00',
            'timezone' => 'Europe/Madrid',
            'duracion_minutos' => 120,
            'activo' => true,
            'email_notificacion' => 'productor@estudio.com',
            'recognize_delivery_from_notification_email' => false,
            'email_copia_notificacion' => 'copia@estudio.com',
        ], $overrides));
    }

    private function makeFakeImapMessage(array $data): object
    {
        return new class($data) {
            public bool $mutatedFlags = false;
            public bool $movedOrDeleted = false;

            public function __construct(private array $data)
            {
            }

            public function getMessageId(): string
            {
                return (string) ($this->data['message_id'] ?? '<msg-1@estudio.com>');
            }

            public function getDate(): Carbon
            {
                return $this->data['date'] ?? Carbon::parse('2026-11-26 10:15:00', 'Europe/Madrid');
            }

            public function getFrom(): object
            {
                $fromEmail = (string) ($this->data['from_email'] ?? 'productor@estudio.com');
                $fromName = (string) ($this->data['from_name'] ?? 'Productor Rock');

                return new class($fromEmail, $fromName) {
                    public function __construct(private string $mail, private string $personal)
                    {
                    }

                    public function first(): object
                    {
                        return (object) ['mail' => $this->mail, 'personal' => $this->personal];
                    }
                };
            }

            public function getReplyTo(): object
            {
                $replyTo = $this->data['reply_to'] ?? null;

                return new class($replyTo) {
                    public function __construct(private ?string $mail)
                    {
                    }

                    public function first(): ?object
                    {
                        return $this->mail ? (object) ['mail' => $this->mail] : null;
                    }
                };
            }

            public function getHeader(): object
            {
                return (object) [
                    'raw' => (string) ($this->data['raw_headers'] ?? "Authentication-Results: mx.google.com;\r\n       dkim=pass header.d=estudio.com;\r\n       spf=pass;\r\n       dmarc=pass\r\n"),
                ];
            }

            public function getSubject(): string
            {
                return (string) ($this->data['subject'] ?? 'Entrega episodio 42');
            }

            public function getTextBody(): string
            {
                return (string) ($this->data['text'] ?? '');
            }

            public function getHTMLBody(): ?string
            {
                return $this->data['html'] ?? null;
            }

            public function getUid(): int
            {
                return (int) ($this->data['uid'] ?? 1001);
            }

            public function setFlag(mixed $flags): void
            {
                $this->mutatedFlags = true;
            }

            public function move(string $folder): void
            {
                $this->movedOrDeleted = true;
            }

            public function delete(): void
            {
                $this->movedOrDeleted = true;
            }
        };
    }

    private function bindMockImapMessages(array $messages): void
    {
        $query = Mockery::mock(WhereQuery::class);
        // Verificar estrictamente que se usa leaveUnread() (FT_PEEK) y since(), nunca unseen()
        $query->shouldReceive('leaveUnread')->atLeast()->once()->andReturnSelf();
        $query->shouldReceive('since')->atLeast()->once()->andReturnSelf();
        $query->shouldReceive('setFetchOrder')->with('desc')->atLeast()->once()->andReturnSelf();
        $query->shouldReceive('limit')->atLeast()->once()->andReturnSelf();
        $query->shouldReceive('unseen')->never();
        $query->shouldReceive('get')->atLeast()->once()->andReturn(new MessageCollection($messages));

        $folder = Mockery::mock(Folder::class);
        $folder->path = 'INBOX';
        $folder->name = 'INBOX';
        $folder->shouldReceive('query')->atLeast()->once()->andReturn($query);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('connect')->atLeast()->once();
        $client->shouldReceive('getFolder')->with('INBOX')->atLeast()->once()->andReturn($folder);
        $client->shouldReceive('disconnect')->atLeast()->once();

        $cm = Mockery::mock(ClientManager::class);
        $cm->shouldReceive('make')->atLeast()->once()->andReturn($client);

        $this->app->instance(ClientManager::class, $cm);
    }

    public function test_link_detector_supports_all_four_platforms_and_only_reliable_expiry(): void
    {
        $detector = app(ExternalDeliveryLinkDetector::class);

        $body = <<<'TXT'
Hola, aquí van los enlaces:
https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view?usp=sharing
https://fromsmash.com/RockSinFronteras-Ep42
https://www.transfernow.net/dl/20261126abcde123
https://we.tl/t-abc123XYZ9
https://wetransfer.com/legal/terms
TXT;

        $links = $detector->extractLinks($body);
        $this->assertCount(4, $links);
        $this->assertSame(ExternalProgramDelivery::PLATFORM_GOOGLE_DRIVE, $links[0]['platform']);
        $this->assertSame(ExternalProgramDelivery::PLATFORM_SMASH, $links[1]['platform']);
        $this->assertSame(ExternalProgramDelivery::PLATFORM_TRANSFERNOW, $links[2]['platform']);
        $this->assertSame(ExternalProgramDelivery::PLATFORM_WETRANSFER, $links[3]['platform']);

        $receivedAt = Carbon::parse('2026-11-26 10:00:00', 'Europe/Madrid');
        $expiryEs = $detector->extractExpiryFromContent('El enlace caduca el 3 de diciembre de 2026.', $receivedAt, 'Europe/Madrid');
        $this->assertNotNull($expiryEs);
        $this->assertSame('2026-12-03', $expiryEs->format('Y-m-d'));

        $expiryEn = $detector->extractExpiryFromContent('This transfer expires on December 5, 2026.', $receivedAt, 'Europe/Madrid');
        $this->assertNotNull($expiryEn);
        $this->assertSame('2026-12-05', $expiryEn->format('Y-m-d'));

        // Sin fecha explícita -> null (nunca inventada)
        $noExpiry = $detector->extractExpiryFromContent('Aquí tienes el episodio de esta semana.', $receivedAt, 'Europe/Madrid');
        $this->assertNull($noExpiry);
    }

    public function test_controlled_message_registers_once_preserves_flags_sends_one_telegram_and_stops_reminders_when_resolved(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]], 200),
        ]);

        $program = $this->createMasterProgram([
            'recognize_delivery_from_notification_email' => true,
        ]);

        $msg = $this->makeFakeImapMessage([
            'message_id' => '<controlled-ep42@estudio.com>',
            'from_email' => 'productor@estudio.com',
            'subject' => 'Rock Sin Fronteras - Ep 42',
            'text' => "Hola, aquí tienes el episodio 42:\nhttps://we.tl/t-rockEp42\nDisponible hasta el 2026-11-30.",
            'date' => Carbon::parse('2026-11-26 10:15:00', 'Europe/Madrid'),
        ]);

        $this->bindMockImapMessages([$msg]);

        /** @var ExternalProgramInboxPoller $poller */
        $poller = app(ExternalProgramInboxPoller::class);

        // 1. Primera consulta: detecta, registra y envía exactamente 1 aviso por Telegram
        $first = $poller->poll();
        $this->assertSame('ok', $first['status']);
        $this->assertSame(1, $first['registered']);
        $this->assertSame(0, $first['duplicates']);
        $this->assertSame(1, $first['notified']);

        // 2. El mensaje mantiene sus indicadores originales (sin marcar leído, mover ni borrar)
        $this->assertFalse($msg->mutatedFlags);
        $this->assertFalse($msg->movedOrDeleted);

        // No se crea ningún RadioProgram ni se distribuye audio en Fase 1
        $this->assertSame(0, RadioProgram::query()->count());

        $delivery = ExternalProgramDelivery::query()->firstOrFail();
        $this->assertSame($program->id, $delivery->master_program_id);
        $this->assertSame(ExternalProgramDelivery::PLATFORM_WETRANSFER, $delivery->platform);
        $this->assertSame('https://we.tl/t-rockEp42', $delivery->delivery_url);
        $this->assertSame(ExternalProgramDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(ExternalProgramDelivery::TELEGRAM_SENT, $delivery->telegram_status);
        $this->assertNotNull($delivery->expires_at);
        $this->assertSame('2026-11-30', $delivery->expires_at->format('Y-m-d'));

        // Verificar que Telegram se envió al chat administrativo (y solo 1 vez)
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.telegram.org/bot123456:TEST-BOT-TOKEN/sendMessage')
                && (string) $request['chat_id'] === '999888777'
                && str_contains((string) $request['text'], 'Rock Sin Fronteras');
        });

        // 3. Segunda consulta: no duplica el pendiente ni el aviso
        $second = $poller->poll();
        $this->assertSame(0, $second['registered']);
        $this->assertSame(1, $second['duplicates']);
        $this->assertSame(1, ExternalProgramDelivery::query()->count());
        Http::assertSentCount(1);

        // 4. Recordatorio diario tras >20h se envía 1 vez; si está aplazado se omite; al resolver se detiene
        /** @var ExternalProgramReminderService $reminderService */
        $reminderService = app(ExternalProgramReminderService::class);

        // Aplazado -> omitido
        $delivery->forceFill(['snoozed_until' => Carbon::parse('2026-11-28 12:00:00', 'Europe/Madrid')])->save();
        $snoozedStats = $reminderService->processReminders(false, Carbon::parse('2026-11-27 12:00:00', 'Europe/Madrid'));
        $this->assertSame(1, $snoozedStats['skipped_snoozed']);
        $this->assertSame(0, $snoozedStats['daily_sent']);
        Http::assertSentCount(1);

        // Sin aplazamiento y >24h -> envía recordatorio diario
        $delivery->forceFill(['snoozed_until' => null])->save();
        $dailyStats = $reminderService->processReminders(false, Carbon::parse('2026-11-27 12:00:00', 'Europe/Madrid'));
        $this->assertSame(1, $dailyStats['daily_sent']);
        Http::assertSentCount(2);

        // Resolver pendiente como descargado -> detiene recordatorios
        $delivery->forceFill([
            'status' => ExternalProgramDelivery::STATUS_DOWNLOADED,
            'resolved_at' => Carbon::parse('2026-11-27 13:00:00', 'Europe/Madrid'),
        ])->save();

        $afterResolve = $reminderService->processReminders(false, Carbon::parse('2026-11-29 12:00:00', 'Europe/Madrid'));
        $this->assertSame(0, $afterResolve['checked']);
        $this->assertSame(0, $afterResolve['daily_sent']);
        $this->assertSame(0, $afterResolve['expiry_sent']);
        Http::assertSentCount(2);
    }

    public function test_cc_and_disabled_programs_are_never_authorized_and_ambiguous_or_unverified_require_review(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        // Programa 1 con reconocimiento activo en correo principal + alternativo compartido
        $p1 = $this->createMasterProgram([
            'nombre' => 'Programa Uno',
            'email_notificacion' => 'p1@estudio.com',
            'recognize_delivery_from_notification_email' => true,
            'email_copia_notificacion' => 'cc-no-autorizado@estudio.com',
        ]);
        MasterProgramDeliverySender::query()->create([
            'master_program_id' => $p1->id,
            'email' => 'compartido@estudio.com',
            'is_active' => true,
        ]);

        // Programa 2 con el mismo alternativo compartido (ambigüedad) y principal desactivado
        $p2 = $this->createMasterProgram([
            'nombre' => 'Programa Dos',
            'email_notificacion' => 'desactivado@estudio.com',
            'recognize_delivery_from_notification_email' => false,
        ]);
        MasterProgramDeliverySender::query()->create([
            'master_program_id' => $p2->id,
            'email' => 'compartido@estudio.com',
            'is_active' => true,
        ]);

        $messages = [
            // CC nunca autorizado -> ignorado
            $this->makeFakeImapMessage([
                'message_id' => '<cc-msg@estudio.com>',
                'from_email' => 'cc-no-autorizado@estudio.com',
                'text' => 'https://fromsmash.com/cc-test',
            ]),
            // Programa con opción desactivada -> ignorado
            $this->makeFakeImapMessage([
                'message_id' => '<disabled-msg@estudio.com>',
                'from_email' => 'desactivado@estudio.com',
                'text' => 'https://fromsmash.com/disabled-test',
            ]),
            // Remitente compartido entre 2 programas -> pendiente de identificar
            $this->makeFakeImapMessage([
                'message_id' => '<ambiguous-msg@estudio.com>',
                'from_email' => 'compartido@estudio.com',
                'text' => 'https://fromsmash.com/ambiguous-ep',
            ]),
            // Correo con fallo de autenticidad (spoofing) -> requiere revisión, sin asignar programa
            $this->makeFakeImapMessage([
                'message_id' => '<spoof-msg@estudio.com>',
                'from_email' => 'p1@estudio.com',
                'raw_headers' => "Authentication-Results: mx.google.com;\r\n       dkim=fail header.d=estudio.com;\r\n       spf=fail;\r\n       dmarc=fail\r\n",
                'text' => 'https://drive.google.com/file/d/spoof123/view',
            ]),
            // Aviso desde plataforma solo con nombre visible (sin Reply-To ni email en cuerpo) -> ignorado
            $this->makeFakeImapMessage([
                'message_id' => '<platform-unknown@wetransfer.com>',
                'from_email' => 'noreply@wetransfer.com',
                'from_name' => 'Carlos Rock',
                'raw_headers' => "Authentication-Results: mx.google.com;\r\n       dkim=pass header.d=wetransfer.com;\r\n       spf=pass;\r\n       dmarc=pass\r\n",
                'text' => 'https://we.tl/t-randomTransfer99',
            ]),
        ];

        $this->bindMockImapMessages($messages);

        $result = app(ExternalProgramInboxPoller::class)->poll();
        $this->assertSame(2, $result['registered']);
        $this->assertSame(3, $result['ignored']);

        $ambiguous = ExternalProgramDelivery::query()->where('message_id', 'ambiguous-msg@estudio.com')->firstOrFail();
        $this->assertNull($ambiguous->master_program_id);
        $this->assertSame(ExternalProgramDelivery::STATUS_NEEDS_REVIEW, $ambiguous->status);
        $this->assertSame(ExternalProgramDelivery::ID_AMBIGUOUS, $ambiguous->identification_status);
        $this->assertSame('Caducidad desconocida', $ambiguous->formattedExpiresAt());

        $spoof = ExternalProgramDelivery::query()->where('message_id', 'spoof-msg@estudio.com')->firstOrFail();
        $this->assertNull($spoof->master_program_id);
        $this->assertSame(ExternalProgramDelivery::STATUS_NEEDS_REVIEW, $spoof->status);
        $this->assertSame('spoofing_detected', $spoof->auth_status);
    }

    public function test_telegram_failure_preserves_pending_delivery_and_records_error(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400),
        ]);

        $this->createMasterProgram([
            'recognize_delivery_from_notification_email' => true,
        ]);

        $msg = $this->makeFakeImapMessage([
            'message_id' => '<tg-fail@estudio.com>',
            'from_email' => 'productor@estudio.com',
            'text' => 'https://www.transfernow.net/dl/20261126failtest',
        ]);

        $this->bindMockImapMessages([$msg]);

        $result = app(ExternalProgramInboxPoller::class)->poll();
        $this->assertSame(1, $result['registered']);
        $this->assertSame(0, $result['notified']);
        $this->assertSame(1, $result['notification_failures']);

        $delivery = ExternalProgramDelivery::query()->firstOrFail();
        $this->assertSame(ExternalProgramDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(ExternalProgramDelivery::TELEGRAM_FAILED, $delivery->telegram_status);
        $this->assertStringContainsString('chat not found', (string) $delivery->telegram_last_error);
    }

    public function test_admin_ui_and_actions_manage_senders_and_deliveries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $program = $this->createMasterProgram([
            'nombre' => 'Piloto Rock',
            'email_notificacion' => 'piloto@estudio.com',
            'recognize_delivery_from_notification_email' => false,
        ]);

        // 1. Actualizar configuración de remitentes en MasterProgram
        $this->actingAs($admin)
            ->put(route('admin.master-programs.update', $program), [
                'nombre' => $program->nombre,
                'conductor' => $program->conductor,
                'dia_transmision' => $program->dia_transmision,
                'timezone' => $program->timezone,
                'duracion_minutos' => $program->duracion_minutos,
                'genero' => $program->genero,
                'email_notificacion' => 'piloto@estudio.com',
                'recognize_delivery_from_notification_email' => '1',
                'delivery_senders_section_present' => '1',
                'delivery_senders' => [
                    ['id' => 0, 'email' => 'alt1@estudio.com', 'label' => 'Estudio B', 'is_active' => '1'],
                    ['id' => 0, 'email' => 'alt2@estudio.com', 'label' => 'Inactivo', 'is_active' => '0'],
                ],
            ])
            ->assertRedirect(route('admin.master-programs.edit', $program));

        $program->refresh();
        $this->assertTrue($program->recognize_delivery_from_notification_email);
        $this->assertSame(['piloto@estudio.com', 'alt1@estudio.com'], $program->authorizedDeliverySenderEmails());

        // 2. Registrar enlace manual y asociar remitente
        $this->actingAs($admin)
            ->post(route('admin.podcast-uploads.external-deliveries.manual'), [
                'delivery_url' => 'https://drive.google.com/file/d/manual123/view',
                'master_program_id' => $program->id,
                'sender_email' => 'nuevo@estudio.com',
                'associate_sender' => '1',
            ])
            ->assertRedirect();

        $delivery = ExternalProgramDelivery::query()->firstOrFail();
        $this->assertSame(ExternalProgramDelivery::PLATFORM_GOOGLE_DRIVE, $delivery->platform);
        $this->assertDatabaseHas('master_program_delivery_senders', [
            'master_program_id' => $program->id,
            'email' => 'nuevo@estudio.com',
            'is_active' => true,
        ]);

        // 3. Ver panel en Emisiones y podcasts con contador
        $this->actingAs($admin)
            ->get(route('admin.podcast-uploads.index'))
            ->assertOk()
            ->assertSee('Programas recibidos')
            ->assertSee('Piloto Rock')
            ->assertSee('Caducidad desconocida');

        // 4. Aplazar y luego marcar como descargado
        $this->actingAs($admin)
            ->post(route('admin.podcast-uploads.external-deliveries.snooze', $delivery), ['hours' => 24])
            ->assertRedirect();
        $this->assertNotNull($delivery->fresh()->snoozed_until);

        $this->actingAs($admin)
            ->post(route('admin.podcast-uploads.external-deliveries.mark-downloaded', $delivery))
            ->assertRedirect();
        $this->assertSame(ExternalProgramDelivery::STATUS_DOWNLOADED, $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->resolved_at);
    }
}
