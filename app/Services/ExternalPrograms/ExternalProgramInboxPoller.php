<?php

declare(strict_types=1);

namespace App\Services\ExternalPrograms;

use App\Models\ExternalProgramDelivery;
use App\Models\ExternalProgramSetting;
use App\Models\MarketingMailAccount;
use App\Models\ThemeSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

class ExternalProgramInboxPoller
{
    public function __construct(
        protected ClientManager $clientManager,
        protected ExternalDeliveryLinkDetector $linkDetector,
        protected ExternalDeliverySenderMatcher $senderMatcher,
        protected ExternalProgramTelegramNotifier $telegramNotifier,
    ) {
    }

    /**
     * Consulta el buzón configurado en modo SOLO LECTURA (FT_PEEK / leaveUnread).
     * Nunca marca mensajes como leídos, nunca mueve ni borra mensajes,
     * no depende de que estén sin leer y deduplica por Message-ID.
     *
     * @return array<string, mixed>
     */
    public function poll(?string $sinceOverride = null, ?string $mailboxOverride = null, int $limit = 50, bool $dryRun = false): array
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
            'notified' => 0,
            'notification_failures' => 0,
            'deliveries' => [],
        ];

        $senderIndex = $this->senderMatcher->buildAuthorizedSenderIndex();
        if ($senderIndex === []) {
            $summary['status'] = 'skipped';
            $summary['reason'] = 'No hay ningún programa con recepción externa habilitada.';
            return $summary;
        }

        $sinceDate = $this->resolveSinceDate($sinceOverride);
        if ($sinceDate === null) {
            $summary['status'] = 'skipped';
            $summary['reason'] = 'No hay fecha de inicio (since_date) configurada; se omite la consulta para evitar avalancha de avisos históricos.';
            return $summary;
        }

        $account = $this->resolveMailboxConfig($mailboxOverride);
        if ($account === null) {
            $summary['status'] = 'error';
            $summary['reason'] = 'No se encontraron credenciales IMAP válidas para el buzón de recepción externa.';
            return $summary;
        }

        $summary['mailbox'] = $account['email'];
        $summary['since'] = $sinceDate->toIso8601String();

        $client = $this->clientManager->make([
            'host' => $account['host'],
            'port' => $account['port'],
            'encryption' => $account['encryption'],
            'validate_cert' => (bool) config('services.imap.validate_cert', false),
            'username' => $account['username'],
            'password' => $account['password'],
            'protocol' => 'imap',
            'options' => [
                'fetch' => IMAP::FT_PEEK,
            ],
        ]);

        $client->connect();

        try {
            $folder = $client->getFolder('INBOX');
            if (! $folder) {
                $summary['status'] = 'error';
                $summary['reason'] = 'No se pudo acceder a la carpeta INBOX.';
                return $summary;
            }

            // Consulta estrictamente en modo solo lectura: leaveUnread() fuerza IMAP::FT_PEEK (BODY.PEEK)
            // y since() acota por fecha sin depender de unseen().
            $query = $folder->query()
                ->leaveUnread()
                ->since($sinceDate->copy()->startOfDay())
                ->setFetchOrder('desc')
                ->limit(max(1, min(200, $limit)));

            $messages = $query->get();

            foreach ($messages as $message) {
                $summary['scanned']++;
                $result = $this->processMessage(
                    message: $message,
                    mailbox: $account['email'],
                    folderName: (string) ($folder->path ?? $folder->name ?? 'INBOX'),
                    sinceDate: $sinceDate,
                    senderIndex: $senderIndex,
                    dryRun: $dryRun
                );

                if ($result['outcome'] === 'duplicate') {
                    $summary['duplicates']++;
                } elseif ($result['outcome'] === 'registered') {
                    $summary['registered']++;
                    if ($result['delivery'] !== null) {
                        $summary['deliveries'][] = $result['delivery'];
                    }
                    if ($result['notified'] === true) {
                        $summary['notified']++;
                    } elseif ($result['notified'] === false) {
                        $summary['notification_failures']++;
                    }
                } else {
                    $summary['ignored']++;
                }
            }
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
                // Ignorar error menor al cerrar sesión IMAP
            }
        }

        return $summary;
    }

    /**
     * Procesa un mensaje IMAP individual sin modificar jamás sus flags ni abrir/descargar enlaces.
     *
     * @param  array<string, \Illuminate\Support\Collection<int, \App\Models\MasterProgram>>  $senderIndex
     * @return array{outcome: 'registered'|'duplicate'|'ignored', delivery: ?ExternalProgramDelivery, notified: ?bool}
     */
    public function processMessage(
        mixed $message,
        string $mailbox,
        string $folderName,
        Carbon $sinceDate,
        array $senderIndex,
        bool $dryRun = false
    ): array {
        $tz = (string) config('app.timezone', 'Europe/Madrid');
        $messageId = $this->extractNormalizedMessageId($message);

        // Deduplicación estricta por Message-ID antes de cualquier otra operación
        if ($messageId !== null && ExternalProgramDelivery::query()->where('message_id', $messageId)->exists()) {
            return ['outcome' => 'duplicate', 'delivery' => null, 'notified' => null];
        }

        $receivedAt = $this->extractReceivedAt($message, $tz);
        if ($receivedAt !== null && $receivedAt->lt($sinceDate)) {
            return ['outcome' => 'ignored', 'delivery' => null, 'notified' => null];
        }

        [$rawFromEmail, $senderName] = $this->extractFromAddress($message);
        $replyToEmail = $this->extractReplyToAddress($message);
        $rawHeaders = $this->extractRawHeaders($message);
        $subject = $this->extractSubject($message);
        $textBody = $this->extractTextBody($message);
        $htmlBody = $this->extractHtmlBody($message);
        $imapUid = $this->extractUid($message);

        // Detectar enlaces compatibles (Google Drive, Smash, TransferNow, WeTransfer) sin abrirlos
        $links = $this->linkDetector->extractLinks($textBody, $htmlBody);
        if ($links === []) {
            return ['outcome' => 'ignored', 'delivery' => null, 'notified' => null];
        }

        // Verificar remitente autorizado + autenticidad SPF/DKIM/DMARC
        $match = $this->senderMatcher->matchMessage(
            rawFromEmail: $rawFromEmail,
            replyToEmail: $replyToEmail,
            rawHeaders: $rawHeaders,
            bodyText: $textBody . "\n" . strip_tags((string) $htmlBody),
            senderIndex: $senderIndex
        );

        if (! $match['should_register']) {
            return ['outcome' => 'ignored', 'delivery' => null, 'notified' => null];
        }

        $primaryLink = $links[0];
        $additionalLinks = count($links) > 1 ? array_slice($links, 1) : null;

        // Caducidad solo si se obtiene de forma fiable del texto; nunca inventada
        $expiresAt = $this->linkDetector->extractExpiryFromContent(
            $textBody . "\n" . strip_tags((string) $htmlBody),
            $receivedAt ?? now($tz),
            $tz
        );

        $attributes = [
            'master_program_id' => $match['master_program_id'],
            'message_id' => $messageId,
            'mailbox' => $mailbox,
            'folder' => $folderName,
            'imap_uid' => $imapUid,
            'source_type' => ExternalProgramDelivery::SOURCE_EMAIL,
            'sender_email' => $match['effective_sender_email'] ?: $rawFromEmail,
            'sender_name' => $senderName,
            'raw_from_email' => $rawFromEmail,
            'reply_to_email' => $replyToEmail,
            'subject' => $subject !== '' ? mb_substr($subject, 0, 500) : null,
            'received_at' => $receivedAt ?? now($tz),
            'received_timezone' => $tz,
            'platform' => $primaryLink['platform'],
            'delivery_url' => $primaryLink['url'],
            'additional_links' => $additionalLinks,
            'expires_at' => $expiresAt,
            'expiry_source' => $expiresAt !== null ? 'email_body' : null,
            'auth_status' => (string) ($match['auth']['status'] ?? 'unverified'),
            'auth_reason' => (string) ($match['auth']['reason'] ?? ''),
            'auth_details' => [
                'spf' => $match['auth']['spf'] ?? null,
                'dkim' => $match['auth']['dkim'] ?? null,
                'dmarc' => $match['auth']['dmarc'] ?? null,
                'is_forwarded' => (bool) ($match['auth']['is_forwarded'] ?? false),
            ],
            'identification_status' => $match['identification_status'],
            'status' => $match['status'],
            'incident_reason' => $match['incident_reason'],
            'telegram_status' => ExternalProgramDelivery::TELEGRAM_PENDING,
        ];

        if ($dryRun) {
            return [
                'outcome' => 'registered',
                'delivery' => new ExternalProgramDelivery($attributes),
                'notified' => null,
            ];
        }

        $delivery = ExternalProgramDelivery::query()->create($attributes);

        // Enviar aviso inicial por Telegram (si falla, conserva el pendiente y registra el fallo)
        $telegramResult = $this->telegramNotifier->sendInitialAlert($delivery);

        return [
            'outcome' => 'registered',
            'delivery' => $delivery->fresh(['masterProgram']),
            'notified' => (bool) $telegramResult['ok'],
        ];
    }

    public function resolveSinceDate(?string $override = null): ?Carbon
    {
        $raw = trim((string) ($override ?? ''));
        if ($raw === '') {
            $raw = (string) (ExternalProgramSetting::getValue('since_date')
                ?? config('services.external_programs.since_date')
                ?? '');
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $tz = (string) config('app.timezone', 'Europe/Madrid');

        try {
            return Carbon::parse($raw, $tz);
        } catch (Throwable) {
            Log::warning("[ExternalProgramInboxPoller] Fecha since_date inválida: {$raw}");
            return null;
        }
    }

    /**
     * Resuelve las credenciales IMAP del buzón designado aprovechando la configuración existente.
     *
     * @return array{email: string, host: string, port: int, encryption: string, username: string, password: string}|null
     */
    public function resolveMailboxConfig(?string $mailboxOverride = null): ?array
    {
        $targetMailbox = strtolower(trim((string) (
            $mailboxOverride
            ?: ExternalProgramSetting::getValue('mailbox_email')
            ?: config('services.external_programs.mailbox')
            ?: ''
        )));

        // 1. Buscar primero en cuentas de correo configuradas en MarketingMailAccount si coincide
        if ($targetMailbox !== '') {
            $account = MarketingMailAccount::query()
                ->whereRaw('LOWER(email) = ?', [$targetMailbox])
                ->where('is_active', true)
                ->first();

            if ($account && filled($account->imap_password)) {
                return [
                    'email' => $account->email,
                    'host' => $account->imap_host ?: (string) config('services.imap.host', 'imap.gmail.com'),
                    'port' => (int) ($account->imap_port ?: config('services.imap.port', 993)),
                    'encryption' => $account->imap_encryption ?: (string) config('services.imap.encryption', 'ssl'),
                    'username' => $account->email,
                    'password' => (string) $account->imap_password,
                ];
            }
        }

        // 2. Fallback a la configuración IMAP estándar de Laravel / ThemeSetting
        $settings = ThemeSetting::current();
        $username = trim((string) ($targetMailbox ?: $settings->imap_username ?: config('services.imap.username') ?: ''));
        $password = trim((string) ($settings->imap_password ?: config('services.imap.password') ?: ''));

        if ($username === '' || $password === '') {
            return null;
        }

        return [
            'email' => $username,
            'host' => (string) config('services.imap.host', 'imap.gmail.com'),
            'port' => (int) config('services.imap.port', 993),
            'encryption' => (string) config('services.imap.encryption', 'ssl'),
            'username' => $username,
            'password' => $password,
        ];
    }

    protected function extractNormalizedMessageId(mixed $message): ?string
    {
        try {
            $raw = method_exists($message, 'getMessageId') ? (string) $message->getMessageId() : '';
            $clean = trim($raw, " \t\n\r\0\x0B<>");
            return $clean !== '' ? mb_substr($clean, 0, 250) : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function extractReceivedAt(mixed $message, string $tz): ?Carbon
    {
        try {
            if (! method_exists($message, 'getDate')) {
                return null;
            }
            $date = $message->getDate();
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance($date)->setTimezone($tz);
            }
            if (is_object($date) && method_exists($date, 'toDate')) {
                $d = $date->toDate();
                if ($d instanceof \DateTimeInterface) {
                    return Carbon::instance($d)->setTimezone($tz);
                }
            }
            $str = trim((string) $date);
            if ($str !== '') {
                return Carbon::parse($str)->setTimezone($tz);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    protected function extractFromAddress(mixed $message): array
    {
        try {
            $from = method_exists($message, 'getFrom') ? $message->getFrom() : null;
            if (is_object($from) && method_exists($from, 'first')) {
                $first = $from->first();
                if (is_object($first)) {
                    $mail = strtolower(trim((string) ($first->mail ?? '')));
                    $personal = trim((string) ($first->personal ?? '')) ?: null;
                    return [$mail, $personal];
                }
            }
            if (is_array($from) && isset($from[0])) {
                $first = $from[0];
                $mail = strtolower(trim((string) ($first->mail ?? $first['mail'] ?? '')));
                $personal = trim((string) ($first->personal ?? $first['personal'] ?? '')) ?: null;
                return [$mail, $personal];
            }
        } catch (Throwable) {
            // Fall through
        }

        return ['', null];
    }

    protected function extractReplyToAddress(mixed $message): ?string
    {
        try {
            if (method_exists($message, 'getReplyTo')) {
                $replyTo = $message->getReplyTo();
                if (is_object($replyTo) && method_exists($replyTo, 'first')) {
                    $first = $replyTo->first();
                    if (is_object($first) && filled($first->mail ?? null)) {
                        return strtolower(trim((string) $first->mail));
                    }
                }
            }
        } catch (Throwable) {
            // Fall through
        }

        return null;
    }

    protected function extractRawHeaders(mixed $message): string
    {
        try {
            if (method_exists($message, 'getHeader')) {
                $header = $message->getHeader();
                if (is_object($header) && isset($header->raw) && is_string($header->raw)) {
                    return $header->raw;
                }
                if (is_string($header)) {
                    return $header;
                }
            }
        } catch (Throwable) {
            // Fall through
        }

        return '';
    }

    protected function extractSubject(mixed $message): string
    {
        try {
            return method_exists($message, 'getSubject') ? trim((string) $message->getSubject()) : '';
        } catch (Throwable) {
            return '';
        }
    }

    protected function extractTextBody(mixed $message): string
    {
        try {
            return method_exists($message, 'getTextBody') ? (string) ($message->getTextBody() ?? '') : '';
        } catch (Throwable) {
            return '';
        }
    }

    protected function extractHtmlBody(mixed $message): ?string
    {
        try {
            if (method_exists($message, 'getHTMLBody')) {
                $html = $message->getHTMLBody();
                return is_string($html) ? $html : null;
            }
        } catch (Throwable) {
            // Fall through
        }

        return null;
    }

    protected function extractUid(mixed $message): ?int
    {
        try {
            if (method_exists($message, 'getUid')) {
                $uid = $message->getUid();
                return is_numeric($uid) ? (int) $uid : null;
            }
        } catch (Throwable) {
            // Fall through
        }

        return null;
    }
}

