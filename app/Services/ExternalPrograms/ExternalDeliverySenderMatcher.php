<?php

declare(strict_types=1);

namespace App\Services\ExternalPrograms;

use App\Models\ExternalProgramDelivery;
use App\Models\MasterProgram;
use App\Services\EmailSecurityVerifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ExternalDeliverySenderMatcher
{
    /**
     * Dominios conocidos desde los que las plataformas envían avisos propios.
     * Nunca se autorizan globalmente todos los correos de estas plataformas.
     */
    protected array $platformRelayDomains = [
        'wetransfer.com',
        'fromsmash.com',
        'transfernow.net',
        'google.com',
    ];

    public function __construct(
        protected EmailSecurityVerifier $securityVerifier
    ) {
    }

    /**
     * Construye el índice [email_normalizado => Collection<MasterProgram>] únicamente
     * con los programas que tienen habilitada la recepción externa.
     * Nunca incluye CC ni destinatarios globales.
     *
     * @return array<string, Collection<int, MasterProgram>>
     */
    public function buildAuthorizedSenderIndex(): array
    {
        $query = MasterProgram::query();
        if (Schema::hasTable('master_program_delivery_senders')) {
            $query->with('deliverySenders');
        }

        $programs = $query->get();
        $index = [];

        foreach ($programs as $program) {
            foreach ($program->authorizedDeliverySenderEmails() as $email) {
                if (! isset($index[$email])) {
                    $index[$email] = collect();
                }
                if (! $index[$email]->contains('id', $program->id)) {
                    $index[$email]->push($program);
                }
            }
        }

        return $index;
    }

    /**
     * Evalúa un correo entrante contra los remitentes autorizados y su autenticidad SPF/DKIM/DMARC.
     *
     * @param  array<string, Collection<int, MasterProgram>>  $senderIndex
     * @return array{
     *     should_register: bool,
     *     master_program_id: ?int,
     *     effective_sender_email: string,
     *     identification_status: string,
     *     status: string,
     *     incident_reason: ?string,
     *     auth: array<string, mixed>,
     * }
     */
    public function matchMessage(
        string $rawFromEmail,
        ?string $replyToEmail,
        string $rawHeaders,
        string $bodyText,
        array $senderIndex
    ): array {
        $from = $this->normalizeEmail($rawFromEmail);
        $replyTo = $this->normalizeEmail($replyToEmail);
        $auth = $this->securityVerifier->verify($from, $rawHeaders);

        // 1. Coincidencia directa por cabecera From:
        if ($from !== '' && isset($senderIndex[$from])) {
            return $this->resolveProgramsForMatchedEmail(
                matchedEmail: $from,
                programs: $senderIndex[$from],
                auth: $auth,
                viaPlatformRelay: false
            );
        }

        // 2. Aviso enviado por la propia plataforma (ej. noreply@wetransfer.com, no-reply@fromsmash.com)
        // Regla: no autorizar todos los mensajes de una plataforma ni atribuir solo por nombre visible.
        if ($from !== '' && $this->isPlatformRelaySender($from)) {
            // 2a. Comprobar Reply-To exacto (correo real, no nombre visible)
            if ($replyTo !== '' && isset($senderIndex[$replyTo])) {
                return $this->resolveProgramsForMatchedEmail(
                    matchedEmail: $replyTo,
                    programs: $senderIndex[$replyTo],
                    auth: $auth,
                    viaPlatformRelay: true
                );
            }

            // 2b. Si un correo autorizado aparece explícitamente en el cuerpo de un aviso de plataforma,
            // no se asocia automáticamente: se registra para revisión manual.
            $mentionedEmail = $this->findExplicitConfiguredEmailInText($bodyText, array_keys($senderIndex));
            if ($mentionedEmail !== null) {
                return [
                    'should_register' => true,
                    'master_program_id' => null,
                    'effective_sender_email' => $mentionedEmail,
                    'identification_status' => ExternalProgramDelivery::ID_UNIDENTIFIED,
                    'status' => ExternalProgramDelivery::STATUS_NEEDS_REVIEW,
                    'incident_reason' => sprintf(
                        'Aviso enviado desde plataforma (%s); la dirección %s solo aparece en el texto del mensaje y la asociación no es fiable. Requiere revisión manual.',
                        $from,
                        $mentionedEmail
                    ),
                    'auth' => $auth,
                ];
            }

            // Correo de plataforma sin vínculo fiable con ningún productor configurado: ignorar.
            return $this->ignoreResult($from, $auth);
        }

        // 3. Remitente desconocido: no provocar descargas ni acciones automáticas.
        return $this->ignoreResult($from, $auth);
    }

    public function isPlatformRelaySender(string $email): bool
    {
        $email = $this->normalizeEmail($email);
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '') {
            return false;
        }

        foreach ($this->platformRelayDomains as $relayDomain) {
            if ($domain === $relayDomain || str_ends_with($domain, '.' . $relayDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, MasterProgram>  $programs
     * @param  array<string, mixed>  $auth
     * @return array<string, mixed>
     */
    protected function resolveProgramsForMatchedEmail(
        string $matchedEmail,
        Collection $programs,
        array $auth,
        bool $viaPlatformRelay
    ): array {
        $isAuthenticated = (bool) ($auth['is_authenticated'] ?? false);
        $isSpoofing = (bool) ($auth['is_spoofing'] ?? false);
        $authReason = (string) ($auth['reason'] ?? 'unverified');

        // Si falla la autenticidad o hay indicios de suplantación, requerir revisión y no asignar automáticamente.
        if ($isSpoofing || ! $isAuthenticated) {
            $reason = $isSpoofing
                ? "Posible suplantación detectada ({$authReason}). Requiere revisión manual antes de confiar en el enlace."
                : "Autenticidad del correo no verificada ({$authReason}). Requiere revisión manual.";

            return [
                'should_register' => true,
                'master_program_id' => null,
                'effective_sender_email' => $matchedEmail,
                'identification_status' => ExternalProgramDelivery::ID_UNIDENTIFIED,
                'status' => ExternalProgramDelivery::STATUS_NEEDS_REVIEW,
                'incident_reason' => $reason,
                'auth' => $auth,
            ];
        }

        // Si el remitente está asociado a varios programas, dejar pendiente la identificación.
        if ($programs->count() > 1) {
            $names = $programs->pluck('nombre')->filter()->implode(', ');

            return [
                'should_register' => true,
                'master_program_id' => null,
                'effective_sender_email' => $matchedEmail,
                'identification_status' => ExternalProgramDelivery::ID_AMBIGUOUS,
                'status' => ExternalProgramDelivery::STATUS_NEEDS_REVIEW,
                'incident_reason' => "El remitente {$matchedEmail} está asociado a varios programas ({$names}). Selecciona el programa correspondiente.",
                'auth' => $auth,
            ];
        }

        /** @var MasterProgram $program */
        $program = $programs->first();

        return [
            'should_register' => true,
            'master_program_id' => (int) $program->id,
            'effective_sender_email' => $matchedEmail,
            'identification_status' => ExternalProgramDelivery::ID_MATCHED,
            'status' => ExternalProgramDelivery::STATUS_PENDING,
            'incident_reason' => $viaPlatformRelay
                ? 'Aviso recibido a través de la plataforma (Reply-To verificado con autenticación de plataforma).'
                : null,
            'auth' => $auth,
        ];
    }

    /**
     * @param  array<int, string>  $configuredEmails
     */
    protected function findExplicitConfiguredEmailInText(string $text, array $configuredEmails): ?string
    {
        if ($configuredEmails === [] || trim($text) === '') {
            return null;
        }

        $lower = strtolower($text);
        foreach ($configuredEmails as $email) {
            if ($email !== '' && preg_match('/(?<![a-z0-9._%+-])' . preg_quote($email, '/') . '(?![a-z0-9._%+-])/i', $lower)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $auth
     * @return array<string, mixed>
     */
    protected function ignoreResult(string $from, array $auth): array
    {
        return [
            'should_register' => false,
            'master_program_id' => null,
            'effective_sender_email' => $from,
            'identification_status' => ExternalProgramDelivery::ID_UNIDENTIFIED,
            'status' => ExternalProgramDelivery::STATUS_NEEDS_REVIEW,
            'incident_reason' => null,
            'auth' => $auth,
        ];
    }

    protected function normalizeEmail(?string $email): string
    {
        $clean = strtolower(trim((string) $email));

        return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : '';
    }
}
