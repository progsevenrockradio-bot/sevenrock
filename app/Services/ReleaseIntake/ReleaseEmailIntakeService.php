<?php

declare(strict_types=1);

namespace App\Services\ReleaseIntake;

use App\Models\MarketingMailAccount;
use App\Models\NewRelease;
use App\Models\ReleaseIntakeSender;
use App\Models\ReleaseIntakeSetting;
use App\Models\ThemeSetting;
use App\Services\EmailSecurityVerifier;
use App\Services\ReleaseScheduleManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

/**
 * Recepción independiente y configurable de propuestas de nuevos lanzamientos musicales.
 *
 * Garantías:
 * - Inicialmente desactivada (ReleaseIntakeSetting::isEnabled() === false).
 * - Modo estrictamente solo lectura en IMAP (IMAP::FT_PEEK + leaveUnread):
 *   nunca modifica banderas, nunca mueve ni borra mensajes, no depende de unseen().
 * - Consulta INBOX y archivados ([Gmail]/Todos / Archive) con búsqueda acotada por since_date.
 * - Verifica autenticidad del remitente (SPF/DKIM/DMARC) con EmailSecurityVerifier.
 * - NUNCA publica automáticamente, NUNCA descarga MP3/ZIP/adjuntos, NUNCA llama a IA de pago.
 * - Deduplica por Message-ID sin sobrescribir fichas publicadas ni fusionar single con álbum.
 */
class ReleaseEmailIntakeService
{
    public function __construct(
        protected ClientManager $clientManager,
        protected EmailSecurityVerifier $securityVerifier,
        protected ReleaseScheduleManager $scheduleManager,
    ) {
    }

    /**
     * Ejecuta una consulta solo lectura sobre el buzón configurado para lanzamientos.
     *
     * @return array<string, mixed>
     */
    public function poll(int $limit = 30, bool $dryRun = false, ?string $sinceOverride = null): array
    {
        $summary = [
            'status' => 'ok',
            'reason' => null,
            'mailbox' => null,
            'since' => null,
            'scanned' => 0,
            'registered' => 0,
            'duplicates' => 0,
            'ignored' => 0,
            'spoofing_held' => 0,
            'releases' => [],
        ];

        if (! ReleaseIntakeSetting::isEnabled()) {
            $summary['status'] = 'disabled';
            $summary['reason'] = 'La recepción de correos de lanzamientos está desactivada hasta configurar un piloto.';

            return $summary;
        }

        $authorizedSenders = ReleaseIntakeSender::query()
            ->active()
            ->get()
            ->keyBy(static fn (ReleaseIntakeSender $s): string => strtolower(trim((string) $s->email)));

        if ($authorizedSenders->isEmpty()) {
            $summary['status'] = 'skipped';
            $summary['reason'] = 'No hay remitentes autorizados activos para recepción de lanzamientos.';

            return $summary;
        }

        $sinceDate = $this->resolveSinceDate($sinceOverride);
        if ($sinceDate === null) {
            $summary['status'] = 'skipped';
            $summary['reason'] = 'Falta configurar la fecha de inicio de consulta (since_date).';

            return $summary;
        }

        $mailboxConfig = $this->resolveMailboxConfig();
        if ($mailboxConfig === null) {
            $summary['status'] = 'error';
            $summary['reason'] = 'No se encontró configuración IMAP válida para el buzón receptor configurado.';

            return $summary;
        }

        $summary['mailbox'] = $mailboxConfig['email'];
        $summary['since'] = $sinceDate->toDateString();

        $client = $this->clientManager->make([
            'host' => $mailboxConfig['host'],
            'port' => $mailboxConfig['port'],
            'encryption' => $mailboxConfig['encryption'],
            'validate_cert' => (bool) config('services.imap.validate_cert', false),
            'username' => $mailboxConfig['username'],
            'password' => $mailboxConfig['password'],
            'protocol' => 'imap',
            'options' => [
                'fetch' => IMAP::FT_PEEK,
            ],
        ]);

        $client->connect();

        try {
            $folders = $this->resolveFoldersToScan($client);
            if ($folders === []) {
                $summary['status'] = 'error';
                $summary['reason'] = 'No se pudo acceder a INBOX ni a carpetas de archivados.';

                return $summary;
            }

            $seenMessageIds = [];
            $imapSince = $sinceDate->copy()->startOfDay();

            foreach ($folders as $folder) {
                $folderName = (string) ($folder->path ?? $folder->name ?? 'INBOX');

                $query = $folder->query()
                    ->leaveUnread()
                    ->since($imapSince)
                    ->setFetchOrder('desc')
                    ->limit(max(1, min(100, $limit)));

                if (method_exists($query, 'setFetchBody') && ! ($query instanceof \Mockery\MockInterface)) {
                    $query->setFetchBody(false);
                }

                foreach ($query->get() as $message) {
                    $msgId = $this->normalizeMessageId((string) ($message->getMessageId()?->first() ?? ''));
                    if ($msgId !== '' && isset($seenMessageIds[strtolower($msgId)])) {
                        continue;
                    }
                    if ($msgId !== '') {
                        $seenMessageIds[strtolower($msgId)] = true;
                    }

                    $summary['scanned']++;
                    $fromAddress = strtolower(trim((string) ($message->getFrom()?->first()?->mail ?? '')));
                    if ($fromAddress === '' || ! $authorizedSenders->has($fromAddress)) {
                        $summary['ignored']++;
                        continue;
                    }

                    // Cargar cuerpo en solo lectura únicamente para remitentes autorizados; NUNCA descargar adjuntos
                    if (method_exists($message, 'parseBody')) {
                        try {
                            $message->parseBody();
                        } catch (Throwable) {
                        }
                    }

                    $rawDate = $message->getDate()?->first();
                    $receivedAt = $rawDate ? Carbon::parse((string) $rawDate) : now();
                    if ($receivedAt->lt($sinceDate->copy()->startOfDay())) {
                        $summary['ignored']++;
                        continue;
                    }

                    $attachmentNames = [];
                    try {
                        if (method_exists($message, 'getAttachments')) {
                            foreach ($message->getAttachments() as $att) {
                                $name = trim((string) ($att->getName() ?? $att->name ?? ''));
                                if ($name !== '') {
                                    $attachmentNames[] = $name;
                                }
                            }
                        }
                    } catch (Throwable) {
                    }

                    $result = $this->ingestAuthorizedMessage([
                        'message_id' => $msgId,
                        'sender_email' => $fromAddress,
                        'subject' => (string) ($message->getSubject()?->first() ?? ''),
                        'body_text' => (string) ($message->getTextBody() ?: strip_tags((string) ($message->getHTMLBody() ?? ''))),
                        'raw_headers' => (string) ($message->getHeader()?->raw ?? ''),
                        'received_at' => $receivedAt,
                        'folder' => $folderName,
                        'mailbox' => $mailboxConfig['email'],
                        'attachments' => $attachmentNames,
                    ], $authorizedSenders->get($fromAddress), $dryRun);

                    if ($result['outcome'] === 'duplicate') {
                        $summary['duplicates']++;
                    } elseif ($result['outcome'] === 'registered') {
                        $summary['registered']++;
                        if (! empty($result['is_spoofing'])) {
                            $summary['spoofing_held']++;
                        }
                        if ($result['release'] !== null) {
                            $summary['releases'][] = $result['release'];
                        }
                    } else {
                        $summary['ignored']++;
                    }
                }
            }
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
            }
        }

        return $summary;
    }

    /**
     * Registra una propuesta de lanzamiento a partir de un mensaje de remitente autorizado.
     * Nunca publica automáticamente ni descarga archivos adjuntos.
     *
     * @param  array<string, mixed>  $payload
     * @return array{outcome: string, release: ?NewRelease, is_spoofing: bool, reason: ?string}
     */
    public function ingestAuthorizedMessage(array $payload, ReleaseIntakeSender $sender, bool $dryRun = false): array
    {
        $messageId = $this->normalizeMessageId((string) ($payload['message_id'] ?? ''));
        $senderEmail = strtolower(trim((string) ($payload['sender_email'] ?? $sender->email)));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $bodyText = trim((string) ($payload['body_text'] ?? ''));
        $rawHeaders = (string) ($payload['raw_headers'] ?? '');
        $receivedAt = $payload['received_at'] instanceof Carbon
            ? $payload['received_at']
            : Carbon::parse((string) ($payload['received_at'] ?? now()));

        // 1. Deduplicación estricta por Message-ID (nunca sobrescribe ni duplica)
        if ($messageId !== '' && NewRelease::query()->where('source_message_id', $messageId)->exists()) {
            return [
                'outcome' => 'duplicate',
                'release' => null,
                'is_spoofing' => false,
                'reason' => 'Message-ID ya registrado previamente.',
            ];
        }

        // 2. Verificar autenticidad técnica del remitente (SPF/DKIM/DMARC)
        $authResult = $this->securityVerifier->verify($senderEmail, $rawHeaders);
        $isSpoofing = (bool) ($authResult['is_spoofing'] ?? false);

        // 3. Evaluar embargo y fecha de lanzamiento musical (reutilizando ReleaseScheduleManager)
        $schedule = $this->scheduleManager->evaluateSchedule($subject, $bodyText, $receivedAt);
        $musicalReleaseDate = $schedule['musical_release_date'] instanceof Carbon
            ? $schedule['musical_release_date']->toDateString()
            : null;

        // 4. Extraer artista, título, tipo y enlaces sin inventar datos ni usar IA de pago
        [$artistName, $releaseTitle] = $this->extractArtistAndTitle($subject, $sender);
        $releaseType = $this->detectReleaseType($subject . ' ' . $bodyText);
        $links = $this->extractLinks($bodyText);
        $proposalSummary = $this->buildProposalSummary($bodyText);
        $usageConditions = $this->extractUsageConditions($bodyText, $schedule['notes'] ?? null);

        if ($dryRun) {
            return [
                'outcome' => 'registered',
                'release' => null,
                'is_spoofing' => $isSpoofing,
                'reason' => 'dry_run',
            ];
        }

        $slug = $this->generateUniqueSlug($releaseTitle . '-' . $artistName);

        $securityStatus = $isSpoofing ? 'held_security' : 'needs_review';
        $incidentReason = $isSpoofing
            ? 'Posible suplantación detectada en cabeceras de autenticación (' . ($authResult['reason'] ?? 'spoofing') . ').'
            : 'Propuesta recibida por correo pendiente de revisión editorial y verificación de derechos.';

        $release = NewRelease::withoutEvents(static function () use (
            $releaseTitle,
            $slug,
            $artistName,
            $releaseType,
            $musicalReleaseDate,
            $receivedAt,
            $links,
            $proposalSummary,
            $schedule,
            $usageConditions,
            $subject,
            $messageId,
            $senderEmail,
            $authResult,
            $securityStatus,
            $incidentReason,
            $sender,
            $payload
        ): NewRelease {
            return NewRelease::query()->create([
                'title' => $releaseTitle,
                'slug' => $slug,
                'artist_name' => $artistName,
                'release_type' => $releaseType,
                'released_at' => $musicalReleaseDate,
                'announced_at' => $receivedAt->toDateString(),
                'published_at' => null,
                'cover_image' => null,
                'cover_source' => null,
                'cover_permission_credit' => null,
                'audio_path' => null,
                'youtube_url' => $links['youtube'],
                'spotify_url' => $links['spotify'],
                'official_url' => $links['official'],
                'download_url' => $links['download'],
                'description' => null,
                'proposal_summary' => $proposalSummary,
                'is_active' => false,
                'show_in_feed' => true,
                'review_status' => NewRelease::REVIEW_PENDING,
                'received_at' => $receivedAt,
                'is_embargo_active' => (bool) ($schedule['is_embargo_active'] ?? false),
                'embargo_until' => $schedule['embargo_until'] ?? null,
                'usage_conditions' => $usageConditions,
                'source_subject' => mb_substr($subject, 0, 255),
                'source_message_id' => $messageId !== '' ? mb_substr($messageId, 0, 255) : null,
                'source_sender_email' => $senderEmail,
                'source_sender_auth' => $authResult,
                'security_status' => $securityStatus,
                'incident_reason' => $incidentReason,
                'author_email' => $senderEmail,
                'origin' => 'email',
                'intake_metadata' => [
                    'mailbox' => $payload['mailbox'] ?? null,
                    'folder' => $payload['folder'] ?? null,
                    'sender_entity_name' => $sender->entity_name,
                    'sender_entity_type' => $sender->entity_type,
                    'attachments_detected' => array_values((array) ($payload['attachments'] ?? [])),
                    'attachments_downloaded' => false,
                    'used_external_ai' => false,
                ],
            ]);
        });

        Log::info('ReleaseEmailIntakeService: propuesta registrada en bandeja Por revisar', [
            'new_release_id' => $release->id,
            'sender' => $senderEmail,
            'message_id' => $messageId,
            'is_spoofing' => $isSpoofing,
            'embargo' => (bool) ($schedule['is_embargo_active'] ?? false),
        ]);

        return [
            'outcome' => 'registered',
            'release' => $release,
            'is_spoofing' => $isSpoofing,
            'reason' => null,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractArtistAndTitle(string $subject, ReleaseIntakeSender $sender): array
    {
        $clean = trim((string) preg_replace('/^(?:re|fwd|fw|rv)\s*:\s*/iu', '', $subject));
        $clean = trim((string) preg_replace('/^\[[^\]]+\]\s*/u', '', $clean));

        foreach ([' - ', ' – ', ' — ', ': '] as $sep) {
            if (str_contains($clean, $sep)) {
                [$left, $right] = array_map('trim', explode($sep, $clean, 2));
                if ($left !== '' && $right !== '') {
                    return [mb_substr($left, 0, 255), mb_substr($right, 0, 255)];
                }
            }
        }

        $artist = trim((string) $sender->entity_name) !== '' ? trim((string) $sender->entity_name) : 'Artista por confirmar';
        $title = $clean !== '' ? mb_substr($clean, 0, 255) : 'Propuesta sin título';

        return [$artist, $title];
    }

    private function detectReleaseType(string $text): string
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b(nuevo\s+sencillo|new\s+single|single|sencillo|videoclip)\b/iu', $lower)) {
            return 'single';
        }
        if (preg_match('/\b(nuevo\s+ep|new\s+ep|\bep\b)\b/iu', $lower)) {
            return 'ep';
        }
        if (preg_match('/\b(nuevo\s+[aá]lbum|new\s+album|disco\s+de\s+estudio|[aá]lbum|lp)\b/iu', $lower)) {
            return 'album';
        }

        return 'other';
    }

    /**
     * @return array{youtube: ?string, spotify: ?string, official: ?string, download: ?string}
     */
    private function extractLinks(string $text): array
    {
        $links = [
            'youtube' => null,
            'spotify' => null,
            'official' => null,
            'download' => null,
        ];

        if (! preg_match_all('~https?://[^\s<>"\'\)\]]+~i', $text, $matches)) {
            return $links;
        }

        foreach (array_unique($matches[0]) as $rawUrl) {
            $url = rtrim(trim($rawUrl), '.,;');
            $lower = strtolower($url);

            if ($links['youtube'] === null && (str_contains($lower, 'youtube.com') || str_contains($lower, 'youtu.be'))) {
                $links['youtube'] = mb_substr($url, 0, 1000);
            } elseif ($links['spotify'] === null && str_contains($lower, 'open.spotify.com')) {
                $links['spotify'] = mb_substr($url, 0, 1000);
            } elseif ($links['download'] === null && preg_match('/(drive\.google\.com|dropbox\.com|wetransfer\.com|we\.tl|fromsmash\.com|transfernow\.net|mega\.nz)/i', $lower)) {
                $links['download'] = mb_substr($url, 0, 1000);
            } elseif ($links['official'] === null && ! preg_match('/(unsubscribe|mailchimp|list-manage|tracking|pixel)/i', $lower)) {
                $links['official'] = mb_substr($url, 0, 1000);
            }
        }

        return $links;
    }

    private function buildProposalSummary(string $bodyText): ?string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', strip_tags($bodyText)));
        if ($clean === '') {
            return null;
        }

        return Str::limit($clean, 1500, '...');
    }

    private function extractUsageConditions(string $bodyText, ?string $scheduleNotes): ?string
    {
        $notes = [];
        if (filled($scheduleNotes)) {
            $notes[] = trim((string) $scheduleNotes);
        }

        if (preg_match('/([^.\n]*(?:embargo|no\s+publicar|promo\s+only|solo\s+prensa|cr[eé]dito|credit|photo\s+by|foto\s+por|derechos)[^.\n]*)/iu', $bodyText, $m)) {
            $notes[] = trim($m[1]);
        }

        return $notes !== [] ? implode(' | ', array_unique($notes)) : null;
    }

    private function resolveSinceDate(?string $override): ?Carbon
    {
        $raw = trim((string) ($override ?? ReleaseIntakeSetting::getValue('since_date', '')));
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, (string) config('app.timezone', 'America/Caracas'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resuelve credenciales IMAP para el buzón configurado en el panel (sin direcciones en código).
     *
     * @return array<string, mixed>|null
     */
    private function resolveMailboxConfig(): ?array
    {
        $configuredEmail = strtolower(trim((string) ReleaseIntakeSetting::getValue('mailbox_email', '')));
        if ($configuredEmail === '') {
            return null;
        }

        // 1. Buscar en cuentas de correo configuradas en la aplicación
        if (class_exists(MarketingMailAccount::class)) {
            $account = MarketingMailAccount::query()
                ->whereRaw('LOWER(email) = ?', [$configuredEmail])
                ->first();
            if ($account && filled($account->imap_host) && filled($account->imap_password)) {
                return [
                    'email' => $configuredEmail,
                    'host' => (string) $account->imap_host,
                    'port' => (int) ($account->imap_port ?: 993),
                    'encryption' => (string) ($account->imap_encryption ?: 'ssl'),
                    'username' => (string) ($account->imap_username ?: $configuredEmail),
                    'password' => (string) $account->imap_password,
                ];
            }
        }

        // 2. Comprobar configuración IMAP global únicamente si coincide con el buzón configurado
        $globalUsername = strtolower(trim((string) config('services.imap.username', '')));
        $globalPassword = (string) config('services.imap.password', '');
        $globalHost = (string) config('services.imap.host', '');
        if ($globalUsername !== '' && $globalUsername === $configuredEmail && $globalHost !== '' && $globalPassword !== '') {
            return [
                'email' => $configuredEmail,
                'host' => $globalHost,
                'port' => (int) config('services.imap.port', 993),
                'encryption' => (string) config('services.imap.encryption', 'ssl'),
                'username' => (string) config('services.imap.username'),
                'password' => $globalPassword,
            ];
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function resolveFoldersToScan(mixed $client): array
    {
        $folders = [];
        $candidates = ['INBOX', '[Gmail]/All Mail', '[Gmail]/Todos', 'Archive', 'Archivados'];

        foreach ($candidates as $name) {
            try {
                $folder = $client->getFolderByPath($name) ?: $client->getFolder($name);
                if ($folder !== null) {
                    $key = strtolower((string) ($folder->path ?? $folder->name ?? $name));
                    $folders[$key] = $folder;
                }
            } catch (Throwable) {
            }
        }

        return array_values($folders);
    }

    private function normalizeMessageId(string $raw): string
    {
        return trim($raw, " \t\n\r\0\x0B<>");
    }

    private function generateUniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'lanzamiento-' . now()->format('YmdHis');
        $slug = $base;
        $counter = 1;
        while (NewRelease::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}

