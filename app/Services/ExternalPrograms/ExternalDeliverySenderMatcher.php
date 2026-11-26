<?php

declare(strict_types=1);

namespace App\Services\ExternalPrograms;

use App\Models\ExternalProgramDelivery;
use App\Models\MasterProgram;
use App\Services\EmailSecurityVerifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        array $senderIndex,
        string $subject = ''
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
                viaPlatformRelay: false,
                subject: $subject,
                bodyText: $bodyText
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
                    viaPlatformRelay: true,
                    subject: $subject,
                    bodyText: $bodyText
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
        bool $viaPlatformRelay,
        string $subject = '',
        string $bodyText = ''
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

        // Si el remitente está asociado a varios programas, intentar desambiguación inequívoca
        // primero por asunto y, si no basta, usando el contenido como apoyo.
        if ($programs->count() > 1) {
            $disambiguated = $this->disambiguateAuthorizedPrograms($programs, $subject, $bodyText);
            if ($disambiguated !== null) {
                return [
                    'should_register' => true,
                    'master_program_id' => (int) $disambiguated->id,
                    'effective_sender_email' => $matchedEmail,
                    'identification_status' => ExternalProgramDelivery::ID_MATCHED,
                    'status' => ExternalProgramDelivery::STATUS_PENDING,
                    'incident_reason' => $viaPlatformRelay
                        ? 'Aviso recibido a través de la plataforma (Reply-To verificado con autenticación de plataforma).'
                        : null,
                    'auth' => $auth,
                ];
            }

            $names = $programs->pluck('nombre')->filter()->implode(', ');

            return [
                'should_register' => true,
                'master_program_id' => null,
                'effective_sender_email' => $matchedEmail,
                'identification_status' => ExternalProgramDelivery::ID_AMBIGUOUS,
                'status' => ExternalProgramDelivery::STATUS_NEEDS_REVIEW,
                'incident_reason' => "El remitente {$matchedEmail} está asociado a varios programas ({$names}) y no hubo coincidencia inequívoca en asunto ni contenido. Selecciona el programa correspondiente.",
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
     * Desambigua entre los programas autorizados de un remitente compartido:
     * 1. Compara el asunto normalizado contra nombres y alias administrables de sus programas autorizados.
     * 2. Solo si el asunto no produce coincidencia, usa el cuerpo del correo como apoyo.
     * 3. Asigna únicamente si existe una coincidencia inequívoca con UN solo programa.
     *
     * @param  Collection<int, MasterProgram>  $programs
     */
    protected function disambiguateAuthorizedPrograms(Collection $programs, string $subject, string $bodyText): ?MasterProgram
    {
        $normalizedSubject = $this->normalizeForMatching($subject);
        if ($normalizedSubject !== '') {
            $subjectMatches = $this->findMatchingProgramsInNormalizedText($programs, $normalizedSubject);
            if ($subjectMatches->count() === 1) {
                return $subjectMatches->first();
            }
            if ($subjectMatches->count() > 1) {
                // Varios programas mencionados en el asunto -> ambiguo, no asignar automáticamente
                return null;
            }
        }

        $normalizedBody = $this->normalizeForMatching($bodyText);
        if ($normalizedBody !== '') {
            $bodyMatches = $this->findMatchingProgramsInNormalizedText($programs, $normalizedBody);
            if ($bodyMatches->count() === 1) {
                return $bodyMatches->first();
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MasterProgram>  $programs
     * @return Collection<int, MasterProgram>
     */
    protected function findMatchingProgramsInNormalizedText(Collection $programs, string $normalizedText): Collection
    {
        return $programs->filter(function (MasterProgram $program) use ($normalizedText): bool {
            foreach ($this->buildRecognitionTerms($program) as $term) {
                if (preg_match('/(?:^| )' . preg_quote($term, '/') . '(?:$| )/', $normalizedText) === 1) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Construye términos normalizados (nombre oficial, variante sin artículo inicial y alias administrables).
     *
     * @return array<int, string>
     */
    protected function buildRecognitionTerms(MasterProgram $program): array
    {
        $terms = [];
        $candidates = array_merge(
            [(string) $program->nombre],
            $program->deliveryRecognitionAliasesList()
        );

        foreach ($candidates as $raw) {
            $norm = $this->normalizeForMatching($raw);
            if (strlen($norm) >= 4) {
                $terms[] = $norm;

                // Variante sin artículo inicial (ej. "los archivos secretos del rock and roll" -> "archivos secretos del rock and roll")
                $withoutArticle = preg_replace('/^(?:el|la|los|las)\s+/', '', $norm) ?: '';
                if (strlen($withoutArticle) >= 4 && $withoutArticle !== $norm) {
                    $terms[] = $withoutArticle;
                }
            }
        }

        return array_values(array_unique($terms));
    }

    protected function normalizeForMatching(string $text): string
    {
        $ascii = Str::ascii(mb_strtolower($text, 'UTF-8'));
        $clean = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?: '';

        return trim(preg_replace('/\s+/', ' ', $clean) ?: '');
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

