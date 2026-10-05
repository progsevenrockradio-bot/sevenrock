<?php

namespace App\Support;

class ProgramTextNormalizer
{
    public static function limpiarConductor(?string $texto): string
    {
        if (empty(trim((string) $texto))) {
            return '';
        }

        $texto = trim($texto);
        $patrones = [
            '/^conducido por:\s*/i',
            '/^conduce por:\s*/i',
            '/^conduce:\s*/i',
            '/^presentado por:\s*/i',
            '/^presenta:\s*/i',
            '/^dirige:\s*/i',
            '/^con:\s*/i',
            '/^host:\s*/i',
        ];

        foreach ($patrones as $patron) {
            $texto = preg_replace($patron, '', $texto);
        }

        $texto = trim($texto);
        
        if (str_starts_with($texto, ':')) {
            $texto = ltrim($texto, ':');
        }

        return trim($texto);
    }

    public static function limpiarDescripcion(?string $texto): string
    {
        if (empty(trim((string) $texto))) {
            return '';
        }

        $texto = trim($texto);
        
        // Partir el texto en párrafos (por saltos de línea dobles o simples)
        $parrafos = preg_split('/\r\n|\r|\n/', $texto);
        $parrafos = array_map('trim', $parrafos);
        $parrafos = array_filter($parrafos, fn ($p) => $p !== '');

        if (empty($parrafos)) {
            return '';
        }

        $resultado = [];
        $ultimoNormalizado = null;

        foreach ($parrafos as $parrafo) {
            $normalizado = strtolower(preg_replace('/\s+/', '', $parrafo));
            if ($normalizado !== $ultimoNormalizado) {
                $resultado[] = $parrafo;
                $ultimoNormalizado = $normalizado;
            }
        }

        return implode("\n\n", $resultado);
    }
}
