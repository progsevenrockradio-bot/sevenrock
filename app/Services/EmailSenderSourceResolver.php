<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailSenderSource;

class EmailSenderSourceResolver
{
    /**
     * Resuelve la fuente (agencia/sello/promotora) a partir de la dirección del remitente.
     *
     * @return array [
     *     'source'     => ?EmailSenderSource,
     *     'match_type' => string ('exact_email'|'verified_domain'|'unknown'),
     *     'status'     => string ('authorized'|'pending'|'blocked'|'suspended'|'unknown'),
     * ]
     */
    public function resolve(string $senderEmail): array
    {
        $senderEmail = strtolower(trim($senderEmail));
        if ($senderEmail === '') {
            return [
                'source'     => null,
                'match_type' => 'unknown',
                'status'     => 'unknown',
            ];
        }

        $domain = substr(strrchr($senderEmail, '@') ?: '', 1);

        // 1. Coincidencia exacta de dirección de correo
        $exactSources = EmailSenderSource::all();
        foreach ($exactSources as $source) {
            if ($source->matchesEmail($senderEmail)) {
                return [
                    'source'     => $source,
                    'match_type' => 'exact_email',
                    'status'     => $source->status,
                ];
            }
        }

        // 2. Coincidencia por dominio verificado explícito
        if ($domain !== '') {
            foreach ($exactSources as $source) {
                if ($source->matchesDomain($domain)) {
                    return [
                        'source'     => $source,
                        'match_type' => 'verified_domain',
                        'status'     => $source->status,
                    ];
                }
            }
        }

        return [
            'source'     => null,
            'match_type' => 'unknown',
            'status'     => 'unknown',
        ];
    }
}
