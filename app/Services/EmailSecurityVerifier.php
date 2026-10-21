<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailSenderSource;
use Illuminate\Support\Facades\Log;

class EmailSecurityVerifier
{
    /**
     * Lista de identificadores de servidores de autenticación (authserv-id) receptores de confianza.
     * Solo las cabeceras Authentication-Results generadas por estos servidores son aceptadas.
     */
    protected array $trustedAuthServIds = [
        'mx.google.com',
        'google.com',
        'imap.gmail.com',
        'hostinger.com',
        'titan.email',
        'mail.hostinger.com',
    ];

    public function __construct(?array $trustedHosts = null)
    {
        if ($trustedHosts !== null) {
            $this->trustedAuthServIds = array_merge($this->trustedAuthServIds, $trustedHosts);
        }
    }

    /**
     * Verifica la autenticidad técnica y alineación del correo utilizando cabeceras RFC822 crudas.
     *
     * @param string $senderEmail Dirección en cabecera From:
     * @param string $rawHeaders Cabeceras completas del mensaje
     * @param EmailSenderSource|null $source Fuente registrada si existe
     * @return array [
     *     'is_authenticated' => bool,
     *     'is_spoofing'      => bool,
     *     'is_forwarded'     => bool,
     *     'status'           => string ('verified'|'unverified'|'spoofing_detected'|'inconclusive'),
     *     'reason'           => string,
     *     'spf'              => ?string,
     *     'dkim'             => ?string,
     *     'dmarc'            => ?string,
     * ]
     */
    public function verify(string $senderEmail, string $rawHeaders, ?EmailSenderSource $source = null): array
    {
        $senderEmail = strtolower(trim($senderEmail));
        $senderDomain = substr(strrchr($senderEmail, '@') ?: '', 1);

        // 1. Extraer cabecera Authentication-Results de infraestructura confiable
        $authResults = $this->extractTrustedAuthenticationResults($rawHeaders);

        if ($authResults === null) {
            // Intentar con Received-SPF directo si existe
            $spfDirect = $this->extractReceivedSpf($rawHeaders);
            if ($spfDirect !== null && str_contains(strtolower($spfDirect), 'pass')) {
                return [
                    'is_authenticated' => true,
                    'is_spoofing'      => false,
                    'is_forwarded'     => false,
                    'status'           => 'verified',
                    'reason'           => 'spf_received_pass',
                    'spf'              => 'pass',
                    'dkim'             => null,
                    'dmarc'            => null,
                ];
            }

            return [
                'is_authenticated' => false,
                'is_spoofing'      => false,
                'is_forwarded'     => false,
                'status'           => 'unverified',
                'reason'           => 'missing_or_untrusted_auth_headers',
                'spf'              => null,
                'dkim'             => null,
                'dmarc'            => null,
            ];
        }

        // 2. Analizar veredictos de SPF, DKIM y DMARC dentro de la cabecera confiable
        $spfStatus = $this->parseVerdict($authResults, 'spf');
        $dkimStatus = $this->parseVerdict($authResults, 'dkim');
        $dmarcStatus = $this->parseVerdict($authResults, 'dmarc');

        // Extraer dominio firmado en DKIM (header.d o header.i)
        $dkimDomain = $this->extractDkimDomain($authResults);

        // 3. Evaluar alineación DKIM con el dominio del remitente o dominios verificados de la fuente
        $dkimAligned = false;
        if ($dkimStatus === 'pass' && $dkimDomain) {
            $dkimAligned = ($dkimDomain === $senderDomain || str_ends_with($senderDomain, '.' . $dkimDomain) || str_ends_with($dkimDomain, '.' . $senderDomain));
            if (! $dkimAligned && $source !== null) {
                $dkimAligned = $source->matchesDomain($dkimDomain);
            }
        }

        // 4. Evaluar posible suplantación (spoofing flagrante)
        // Ocurre cuando DMARC falla O cuando DKIM y SPF fallan simultáneamente
        if ($dmarcStatus === 'fail' || ($dkimStatus === 'fail' && ($spfStatus === 'fail' || $spfStatus === 'softfail'))) {
            Log::warning("EmailSecurityVerifier: Spoofing detectado para remitente {$senderEmail}", [
                'spf' => $spfStatus,
                'dkim' => $dkimStatus,
                'dmarc' => $dmarcStatus,
            ]);

            return [
                'is_authenticated' => false,
                'is_spoofing'      => true,
                'is_forwarded'     => false,
                'status'           => 'spoofing_detected',
                'reason'           => 'dmarc_dkim_spf_all_failed',
                'spf'              => $spfStatus,
                'dkim'             => $dkimStatus,
                'dmarc'            => $dmarcStatus,
            ];
        }

        // 5. Manejo de correos reenviados (SPF falla por cambio de IP, pero DKIM sobrevive alineado)
        if ($spfStatus === 'fail' || $spfStatus === 'softfail') {
            if ($dkimStatus === 'pass' && $dkimAligned) {
                return [
                    'is_authenticated' => true,
                    'is_spoofing'      => false,
                    'is_forwarded'     => true,
                    'status'           => 'verified',
                    'reason'           => 'forwarded_with_valid_aligned_dkim',
                    'spf'              => $spfStatus,
                    'dkim'             => $dkimStatus,
                    'dmarc'            => $dmarcStatus,
                ];
            }
        }

        // 6. Autenticación exitosa estándar (DKIM alineado o SPF alineado con DMARC no fallido)
        if (($dkimStatus === 'pass' && $dkimAligned) || ($spfStatus === 'pass' && $dmarcStatus !== 'fail')) {
            return [
                'is_authenticated' => true,
                'is_spoofing'      => false,
                'is_forwarded'     => false,
                'status'           => 'verified',
                'reason'           => 'cryptographically_verified',
                'spf'              => $spfStatus,
                'dkim'             => $dkimStatus,
                'dmarc'            => $dmarcStatus,
            ];
        }

        // 7. Resultados no concluyentes (neutral, temperror, permerror, o falta de alineación)
        return [
            'is_authenticated' => false,
            'is_spoofing'      => false,
            'is_forwarded'     => false,
            'status'           => 'inconclusive',
            'reason'           => 'auth_results_inconclusive_or_unaligned',
            'spf'              => $spfStatus,
            'dkim'             => $dkimStatus,
            'dmarc'            => $dmarcStatus,
        ];
    }

    /**
     * Valida de manera estricta si un mensaje atribuido a Dark Vader proviene genuinamente de su cuenta.
     */
    public function isDarkVaderLegitimate(string $senderEmail, string $rawHeaders): bool
    {
        if (strtolower(trim($senderEmail)) !== 'dark.vader.agent@gmail.com') {
            return false;
        }

        $authResults = $this->extractTrustedAuthenticationResults($rawHeaders);
        if ($authResults === null) {
            return false;
        }

        $dkim = $this->parseVerdict($authResults, 'dkim');
        $dkimDomain = $this->extractDkimDomain($authResults);

        // Gmail firma siempre con d=gmail.com
        return ($dkim === 'pass' && in_array($dkimDomain, ['gmail.com', 'google.com'], true));
    }

    /**
     * Extrae únicamente la cabecera Authentication-Results generada por un authserv-id de confianza.
     * Descarta cabeceras inyectadas por remitentes externos o servidores no reconocidos.
     */
    protected function extractTrustedAuthenticationResults(string $rawHeaders): ?string
    {
        // Dividir las cabeceras preservando continuaciones de línea (folding RFC 2822)
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $rawHeaders);
        $lines = preg_split("/\r?\n/", $unfolded ?: '') ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^Authentication-Results:\s*([^\s;]+)(.*)$/i', trim($line), $matches)) {
                $authServId = strtolower(trim($matches[1]));
                $restOfHeader = $matches[2];

                foreach ($this->trustedAuthServIds as $trusted) {
                    if ($authServId === $trusted || str_ends_with($authServId, '.' . $trusted)) {
                        return $restOfHeader;
                    }
                }
            }
        }

        return null;
    }

    protected function extractReceivedSpf(string $rawHeaders): ?string
    {
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $rawHeaders);
        $lines = preg_split("/\r?\n/", $unfolded ?: '') ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^Received-SPF:\s*(pass|fail|softfail|neutral|none)/i', trim($line), $m)) {
                return strtolower($m[1]);
            }
        }

        return null;
    }

    protected function parseVerdict(string $headerBody, string $mechanism): ?string
    {
        // Buscar mechanism=verdict (ej: dkim=pass, spf=fail, dmarc=pass)
        if (preg_match('/\b' . preg_quote($mechanism, '/') . '=([a-z0-9_-]+)/i', $headerBody, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return null;
    }

    protected function extractDkimDomain(string $headerBody): ?string
    {
        if (preg_match('/\bheader\.(?:d|i)=@?([a-z0-9.-]+)/i', $headerBody, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return null;
    }
}
