<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeExternalHttpsUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('El enlace debe ser una cadena de texto válida.');
            return;
        }

        $url = trim($value);

        if ($url === '') {
            return;
        }

        // 1. Debe comenzar estrictamente por https:// (no se permite http:// plano ni esquemas alternativos)
        if (! str_starts_with(strtolower($url), 'https://')) {
            $fail('El enlace debe utilizar el protocolo seguro HTTPS (https://...).');
            return;
        }

        // 2. Prohibir esquemas peligrosos en cualquier parte del payload inicial
        if (preg_match('/^(javascript|data|file|vbscript|about):/i', $url)) {
            $fail('La URL contiene un esquema o protocolo no permitido.');
            return;
        }

        // 3. Validación de sintaxis de URL
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $fail('El enlace no tiene un formato de URL válido.');
            return;
        }

        $parts = parse_url($url);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            $fail('La URL no contiene un host o dominio válido.');
            return;
        }

        if (strtolower($parts['scheme']) !== 'https') {
            $fail('Solo se permiten enlaces con protocolo seguro HTTPS.');
            return;
        }

        $host = strtolower(trim($parts['host']));

        // 4. Prohibir hosts locales explícitos
        $forbiddenHosts = [
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
        ];
        if (in_array($host, $forbiddenHosts, true)) {
            $fail('No se permiten enlaces a direcciones locales o privadas.');
            return;
        }

        // 5. Prohibir TLDs y extensiones locales o reservadas
        if (preg_match('/\.(test|local|localhost|internal|example|invalid|onion|lan|corp)$/i', $host)) {
            $fail('No se permiten destinos de prueba, locales o privados.');
            return;
        }

        // Hosts sin punto (nombres de red interna como 'intranet', 'servidor')
        if (! str_contains($host, '.')) {
            $fail('El dominio del enlace debe ser una dirección pública de internet.');
            return;
        }

        // 6. Prohibir rangos de IP privadas/reservadas
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $fail('No se permiten direcciones IP privadas ni reservadas.');
                return;
            }
        }
    }
}

