<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketingContact;
use Illuminate\Support\Str;

class ArtistEmailMatcher
{
    /**
     * Empareja artista y opcionalmente sello contra marketing_contacts activos.
     *
     * @return array{email_artista: ?string, email_sello: ?string}
     */
    public function matchArtist(string $artista, ?string $sello = null): array
    {
        $emailArtista = $this->findContactEmail($artista);
        $emailSello = (!empty($sello) && trim($sello) !== '') ? $this->findContactEmail($sello) : null;

        return [
            'email_artista' => $emailArtista,
            'email_sello'   => $emailSello,
        ];
    }

    /**
     * Busca el email de un contacto activo por coincidencia exacta o parcial.
     */
    public function findContactEmail(string $search): ?string
    {
        $normalizedSearch = $this->normalize($search);
        if ($normalizedSearch === '' || mb_strlen($normalizedSearch) < 2) {
            return null;
        }

        $contacts = MarketingContact::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('company_or_band')
                  ->orWhereNotNull('name');
            })
            ->get(['id', 'email', 'name', 'company_or_band']);

        if ($contacts->isEmpty()) {
            return null;
        }

        // 1. Coincidencia exacta
        foreach ($contacts as $contact) {
            $normCompany = $this->normalize((string) $contact->company_or_band);
            $normName = $this->normalize((string) $contact->name);

            if (($normCompany !== '' && $normCompany === $normalizedSearch) ||
                ($normName !== '' && $normName === $normalizedSearch)) {
                return $contact->email;
            }
        }

        // 2. Coincidencia parcial (contains)
        if (mb_strlen($normalizedSearch) >= 3) {
            foreach ($contacts as $contact) {
                $normCompany = $this->normalize((string) $contact->company_or_band);
                $normName = $this->normalize((string) $contact->name);

                if ($normCompany !== '' && $this->matchesContains($normCompany, $normalizedSearch)) {
                    return $contact->email;
                }

                if ($normName !== '' && $this->matchesContains($normName, $normalizedSearch)) {
                    return $contact->email;
                }
            }
        }

        return null;
    }

    /**
     * Normaliza el texto: Str::ascii(), minúsculas, quita puntuación y sufijos habituales.
     */
    public function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));

        // Quita puntuación (reemplazar por espacio)
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        // Quitar sufijos/palabras comunes
        $patterns = [
            '/\b(management|oficial|official|records|recordings|band|grupo|music|musica|producciones|productions|cia|co)\b/iu',
        ];
        $text = preg_replace($patterns, ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function matchesContains(string $haystack, string $needle): bool
    {
        if (mb_strlen($needle) < 3 || mb_strlen($haystack) < 3) {
            return false;
        }

        return str_contains($haystack, $needle) || str_contains($needle, $haystack);
    }
}
