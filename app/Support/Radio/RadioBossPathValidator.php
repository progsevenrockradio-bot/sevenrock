<?php

declare(strict_types=1);

namespace App\Support\Radio;

use App\Jobs\UploadRadiobossJob;
use App\Models\RadioProgram;
use Illuminate\Support\Str;

final class RadioBossPathValidator
{
    /**
     * Caracteres prohibidos en nombres de archivo para sistemas Windows y servidores FTP.
     */
    public const FORBIDDEN_CHARACTERS_REGEX = '/[\/\\\\:*?"<>|]/';

    /**
     * Nombres de dispositivo reservados en el sistema de archivos de Windows (DOS device names).
     */
    public const WINDOWS_RESERVED_NAMES = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
    ];

    /**
     * Determina si un nombre de archivo remoto es 100% ASCII y seguro para RadioBOSS.
     */
    public static function isSafeRemoteName(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $trimmed = trim($name);

        // No puede contener referencias a directorios
        if (str_contains($trimmed, '/') || str_contains($trimmed, '\\') || str_contains($trimmed, '..')) {
            return false;
        }

        // Debe terminar en .mp3
        if (! str_ends_with(strtolower($trimmed), '.mp3')) {
            return false;
        }

        // Solo caracteres ASCII imprimibles (32 a 126)
        if (preg_match('/[^\x20-\x7E]/', $trimmed)) {
            return false;
        }

        // No puede contener caracteres prohibidos por Windows/FTP
        if (preg_match(self::FORBIDDEN_CHARACTERS_REGEX, $trimmed)) {
            return false;
        }

        // El cuerpo del nombre no puede estar vacío ni coincidir con nombres reservados de Windows
        $stem = pathinfo($trimmed, PATHINFO_FILENAME);
        $trimmedStem = trim($stem);

        if ($trimmedStem === '' || in_array(strtoupper($trimmedStem), self::WINDOWS_RESERVED_NAMES, true)) {
            return false;
        }

        return true;
    }

    /**
     * Determina si la carpeta remota es segura y compatible con RadioBOSS.
     */
    public static function isSafeRemoteFolder(?string $folder): bool
    {
        if ($folder === null || trim($folder) === '') {
            return false;
        }

        $trimmed = trim(str_replace('\\', '/', $folder), '/');

        if ($trimmed === '' || strtolower($trimmed) === 'programas') {
            return false;
        }

        if (str_contains($trimmed, '..')) {
            return false;
        }

        // Solo ASCII imprimible
        if (preg_match('/[^\x20-\x7E]/', $trimmed)) {
            return false;
        }

        // No caracteres prohibidos de Windows
        if (preg_match('/[:*?"<>|]/', $trimmed)) {
            return false;
        }

        return true;
    }

    /**
     * Detecta incompatibilidades técnicas en la ruta y el nombre remoto.
     *
     * @return list<string>
     */
    public static function detectIncompatibilities(?string $folder, ?string $filename): array
    {
        $issues = [];

        if ($filename === null || trim($filename) === '') {
            $issues[] = 'El nombre de archivo remoto no está definido o está vacío.';
        } else {
            $trimmedName = trim($filename);
            if (! str_ends_with(strtolower($trimmedName), '.mp3')) {
                $issues[] = 'El archivo debe tener extensión .mp3.';
            }
            if (preg_match('/[^\x20-\x7E]/', $trimmedName)) {
                $issues[] = 'El nombre contiene caracteres no ASCII o con tildes que el servidor FTP de RadioBOSS rechaza.';
            }
            if (preg_match(self::FORBIDDEN_CHARACTERS_REGEX, $trimmedName)) {
                $issues[] = 'El nombre contiene caracteres prohibidos en el sistema de archivos (*, ?, :, ", <, >, |, /, \\).';
            }
            if (str_contains($trimmedName, '..')) {
                $issues[] = 'El nombre contiene referencias relativas inválidas (..).';
            }
            $stemCheck = trim(pathinfo($trimmedName, PATHINFO_FILENAME));
            if ($stemCheck !== '' && in_array(strtoupper($stemCheck), self::WINDOWS_RESERVED_NAMES, true)) {
                $issues[] = 'El nombre coincide con un dispositivo reservado de Windows (ej. CON, PRN, AUX, NUL, COM1-9, LPT1-9).';
            }
        }

        if ($folder === null || trim($folder) === '') {
            $issues[] = 'La carpeta remota de emisión no está definida o no tiene correspondencia comprobada.';
        } else {
            $trimmedFolder = trim($folder);
            if (strtolower(trim($trimmedFolder, '/')) === 'programas') {
                $issues[] = "Se rechaza 'Programas' como destino genérico sin correspondencia comprobada.";
            }
            if (preg_match('/[^\x20-\x7E]/', $trimmedFolder)) {
                $issues[] = 'La carpeta remota contiene caracteres no ASCII.';
            }
            if (preg_match('/[:*?"<>|]/', $trimmedFolder)) {
                $issues[] = 'La carpeta remota contiene caracteres prohibidos en el servidor.';
            }
            if (str_contains($trimmedFolder, '..')) {
                $issues[] = 'La carpeta remota contiene referencias relativas inválidas (..).';
            }
        }

        return $issues;
    }

    /**
     * Genera una propuesta técnica ASCII segura a partir de cualquier nombre de archivo.
     */
    public static function generateSafeProposal(string $rawFilename): string
    {
        $basename = basename(str_replace('\\', '/', trim($rawFilename)));
        $stem = pathinfo($basename, PATHINFO_FILENAME);

        // Transliterar caracteres acentuados (ej. CÓDIGO -> CODIGO)
        $asciiStem = (string) Str::ascii($stem);

        // Eliminar referencias relativas
        $asciiStem = str_replace('..', '-', $asciiStem);

        // Reemplazar caracteres prohibidos con '-'
        $sanitizedStem = (string) preg_replace(self::FORBIDDEN_CHARACTERS_REGEX, '-', $asciiStem);
        // Limpiar cualquier carácter que no sea ASCII imprimible
        $sanitizedStem = (string) preg_replace('/[^\x20-\x7E]/', '-', $sanitizedStem);
        // Normalizar espacios y guiones múltiples
        $sanitizedStem = trim((string) preg_replace('/\s+/', ' ', $sanitizedStem));
        $sanitizedStem = trim((string) preg_replace('/-+/', '-', $sanitizedStem));

        if ($sanitizedStem === '' || $sanitizedStem === '-') {
            $sanitizedStem = 'episode';
        }

        // Si coincide con un nombre reservado de Windows, sufijar para evitar rechazo
        if (in_array(strtoupper($sanitizedStem), self::WINDOWS_RESERVED_NAMES, true)) {
            $sanitizedStem .= '-episode';
        }

        $proposal = $sanitizedStem . '.mp3';

        // Autovalidación: garantizar que la propuesta supere estrictamente su propia validación
        if (! self::isSafeRemoteName($proposal)) {
            $proposal = 'episode-' . date('Ymd') . '.mp3';
        }

        return $proposal;
    }

    /**
     * Analiza el episodio para la pantalla de administración y para validación pre-FTP.
     *
     * @return array{
     *     predicted_folder: ?string,
     *     original_filename: string,
     *     confirmed_filename: ?string,
     *     has_confirmed: bool,
     *     effective_filename: string,
     *     safe_proposal: string,
     *     is_safe: bool,
     *     original_already_safe: bool,
     *     needs_confirmation: bool,
     *     is_delivered: bool,
     *     issues: list<string>,
     *     full_remote_path: string
     * }
     */
    public static function analyzeEpisode(RadioProgram $radioProgram): array
    {
        $job = new UploadRadiobossJob($radioProgram->id);
        $folder = $job->resolveRemoteFolder($radioProgram);

        // Nombre derivado original (conserva caracteres editoriales/tildes para R2 y Archive.org)
        $sourcePath = (string) ($radioProgram->archivo_mp3 ?: $radioProgram->buildCanonicalFilename());
        $originalFilename = basename(str_replace('\\', '/', trim($sourcePath)));
        if (! str_ends_with(strtolower($originalFilename), '.mp3')) {
            $originalFilename .= '.mp3';
        }

        $confirmedFilename = $radioProgram->getConfirmedRadiobossFilename();
        $hasConfirmed = $confirmedFilename !== null && trim($confirmedFilename) !== '';

        $safeProposal = self::generateSafeProposal($originalFilename);

        $effectiveFilename = $hasConfirmed
            ? trim((string) $confirmedFilename)
            : ($radioProgram->isDeliveredToRadioboss()
                ? basename((string) data_get($radioProgram->radioboss_metadata, 'remote_path', $safeProposal))
                : $safeProposal);

        $issues = self::detectIncompatibilities($folder, $effectiveFilename);
        $originalIssues = self::detectIncompatibilities($folder, $originalFilename);
        $originalAlreadySafe = empty($originalIssues);

        $isDelivered = $radioProgram->isDeliveredToRadioboss();
        $needsConfirmation = ! $isDelivered && ! $originalAlreadySafe && ! $hasConfirmed;
        $activeIssues = $hasConfirmed ? $issues : $originalIssues;
        $isSafe = empty($activeIssues);

        $fullRemotePath = ($folder !== null && $folder !== '')
            ? trim($folder, '/') . '/' . $effectiveFilename
            : $effectiveFilename;

        return [
            'predicted_folder' => $folder,
            'original_filename' => $originalFilename,
            'confirmed_filename' => $confirmedFilename,
            'has_confirmed' => $hasConfirmed,
            'effective_filename' => $effectiveFilename,
            'safe_proposal' => $safeProposal,
            'is_safe' => $isSafe,
            'original_already_safe' => $originalAlreadySafe,
            'needs_confirmation' => $needsConfirmation,
            'is_delivered' => $isDelivered,
            'issues' => $activeIssues,
            'full_remote_path' => $fullRemotePath,
        ];
    }
}
