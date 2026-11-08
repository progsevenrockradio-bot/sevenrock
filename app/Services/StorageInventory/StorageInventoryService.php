<?php

declare(strict_types=1);

namespace App\Services\StorageInventory;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Inventario de almacenamiento local en modo SOLO LECTURA.
 *
 * Garantías:
 *  - No escribe, mueve, renombra ni borra archivos; no modifica la base de datos
 *    (solo SELECT) ni objetos de R2 (solo HEAD/GET mediante R2ObjectInspector).
 *  - Recorre únicamente las raíces declaradas en ROOTS (relativas a la base).
 *  - No sigue enlaces simbólicos: los registra y los omite. Además descarta
 *    cualquier ruta cuyo realpath quede fuera de la raíz inspeccionada.
 *  - Un temporal no se considera libre solo por no aparecer en la cola: se
 *    comprueban trabajos pendientes, reservados (en ejecución), diferidos
 *    (reintentos), fallidos (reintentables), episodios en curso, referencias
 *    en base de datos, antigüedad mínima y, en Linux, descriptores abiertos.
 *  - Una copia solo se clasifica como duplicado verificado (A) si el objeto R2
 *    existe, el SHA-256 calculado en streaming coincide con el archivo local y
 *    la aplicación ya no usa la ruta local. Tamaño igual o ETag no bastan.
 */
class StorageInventoryService
{
    public const CAT_A = 'A_duplicado_verificado';
    public const CAT_B = 'B_temporal_libre';
    public const CAT_C = 'C_conservar';
    public const CAT_D = 'D_incierto';

    /**
     * Raíces inspeccionadas, relativas a la base (storage/app), con su rol.
     * El orden importa: la primera raíz que contiene un archivo determina su rol.
     */
    public const ROOTS = [
        'public/podcast-inbox/covers' => 'imagen_subida',
        'public/podcast-inbox' => 'podcast_original',
        'public/programas_procesados' => 'podcast_procesado',
        'tmp/podcast-processing' => 'temporal_procesamiento',
        'tmp/r2' => 'temporal_descarga_r2',
        'tmp/backblaze' => 'temporal_descarga_b2',
        'podcast-chunks' => 'temporal_subida_fragmentada',
        'temp' => 'temp_heredado',
        'public' => 'medio_publico',
    ];

    /** Patrón de archivos temporales de ExtractReleaseCoverJob en la raíz de la base. */
    public const ROOT_TEMP_GLOB = 'temp_id3_*.mp3';

    private const TEMP_ROLES = [
        'temporal_procesamiento',
        'temporal_descarga_r2',
        'temporal_descarga_b2',
        'temporal_subida_fragmentada',
        'temporal_id3',
    ];

    /** Tablas donde se buscan referencias a nombres de archivo (columnas filtradas por patrón). */
    private const REFERENCE_TABLES = [
        'radio_programs', 'radio_program_events', 'master_programs', 'master_program_emisiones',
        'theme_settings', 'posts', 'albums', 'songs', 'radio_artists', 'new_releases', 'events',
        'products', 'gallery_images', 'videos', 'talents', 'talent_media', 'talent_albums',
        'track_submissions', 'community_posts', 'email_post_sanitized_meta',
    ];

    private const REFERENCE_COLUMN_PATTERN = '/(image|imagen|cover|caratula|logo|path|photo|foto|banner|thumb|featured|slide|archivo|file|audio|mp3|url|content|metadata|payload|media)/i';

    private string $base;
    private bool $deepVerify;
    private int $minAgeHours;
    private Carbon $now;

    /** @var array<string, array<int, string>> */
    private array $referenceColumns = [];

    /** @var array<int, array<string, mixed>> */
    private array $jobs = [];

    /** @var array<int, array<string, mixed>> */
    private array $failedJobs = [];

    /** @var array<int, array<string, mixed>> */
    private array $episodes = [];

    /** @var array<string, array<string, mixed>> */
    private array $episodesByRawPath = [];

    /** @var array<string, true>|null */
    private ?array $openFiles = null;

    private bool $queueInspectable = false;
    private string $queueDriver = '';

    /** @var array<int, string> */
    private array $skippedSymlinks = [];

    /** @var array<int, string> */
    private array $warnings = [];

    private bool $dbAvailable = true;

    public function __construct(private readonly R2ObjectInspector $r2)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(?string $base = null, bool $deepVerify = false, int $minAgeHours = 24): array
    {
        $this->base = rtrim(str_replace('\\', '/', $base ?? storage_path('app')), '/');
        $this->deepVerify = $deepVerify;
        $this->minAgeHours = max(1, $minAgeHours);
        $this->now = Carbon::now();
        $this->skippedSymlinks = [];
        $this->warnings = [];

        $this->loadContext();

        $files = $this->collectFiles();
        $items = [];
        foreach ($files as $file) {
            $items[] = $this->classify($file);
        }

        return [
            'meta' => [
                'generated_at' => $this->now->toIso8601String(),
                'hostname' => (string) gethostname(),
                'app_env' => (string) app()->environment(),
                'base_path' => $this->base,
                'roots_inspected' => array_values(array_filter(
                    array_map(fn (string $r) => $this->base . '/' . $r, array_keys(self::ROOTS)),
                    fn (string $p) => is_dir($p)
                )),
                'roots_absent' => array_values(array_filter(
                    array_map(fn (string $r) => $this->base . '/' . $r, array_keys(self::ROOTS)),
                    fn (string $p) => ! is_dir($p)
                )),
                'root_temp_glob' => $this->base . '/' . self::ROOT_TEMP_GLOB,
                'excluded_by_design' => [
                    $this->base . '/private (datos privados de la aplicación)',
                    $this->base . '/* (archivos sueltos en la raíz: parches, copias SQLite, certificados, listas M3U)',
                ],
                'queue_driver' => $this->queueDriver,
                'queue_inspectable' => $this->queueInspectable,
                'r2_configured' => $this->r2->isConfigured(),
                'r2_bucket' => $this->r2->isConfigured() ? $this->r2->bucket() : null,
                'deep_verify' => $this->deepVerify,
                'min_age_hours' => $this->minAgeHours,
                'open_file_check' => $this->openFiles === null ? 'no_disponible' : 'proc_fd',
                'skipped_symlinks' => $this->skippedSymlinks,
                'warnings' => $this->warnings,
            ],
            'summary' => $this->summarize($items),
            'items' => $items,
        ];
    }

    // ---------------------------------------------------------------------
    // Contexto (solo SELECT)
    // ---------------------------------------------------------------------

    private function loadContext(): void
    {
        $this->queueDriver = (string) config('queue.default');
        $this->queueInspectable = false;
        $this->jobs = [];
        $this->failedJobs = [];
        $this->episodes = [];
        $this->episodesByRawPath = [];
        $this->referenceColumns = [];
        $this->dbAvailable = true;

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->dbAvailable = false;
            $this->warnings[] = 'Base de datos no disponible (' . class_basename($e) . '); ningún temporal se clasificará como libre y no se pueden comprobar referencias en BD.';
            $this->openFiles = $this->loadOpenFiles();

            return;
        }

        if ($this->queueDriver === 'database' && $this->hasTable('jobs')) {
            $this->queueInspectable = true;
            try {
                $this->jobs = DB::table('jobs')
                    ->select(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at'])
                    ->get()->map(fn ($r) => (array) $r)->all();
            } catch (Throwable $e) {
                $this->queueInspectable = false;
                $this->warnings[] = 'No se pudo leer la tabla jobs: ' . class_basename($e);
            }
        } else {
            $this->warnings[] = "La cola usa el driver «{$this->queueDriver}»; no es inspeccionable desde base de datos. Ningún temporal se clasificará como libre.";
        }

        if ($this->hasTable('failed_jobs')) {
            try {
                $this->failedJobs = DB::table('failed_jobs')->select(['id', 'payload', 'failed_at'])
                    ->get()->map(fn ($r) => (array) $r)->all();
            } catch (Throwable $e) {
                $this->warnings[] = 'No se pudo leer failed_jobs: ' . class_basename($e);
            }
        }

        if ($this->hasTable('job_batches')) {
            try {
                $pendingBatches = DB::table('job_batches')->where('pending_jobs', '>', 0)->whereNull('finished_at')->whereNull('cancelled_at')->count();
                if ($pendingBatches > 0) {
                    $this->warnings[] = "Hay {$pendingBatches} lote(s) de trabajos sin terminar.";
                }
            } catch (Throwable) {
                // tabla con otro esquema: se ignora
            }
        }

        if ($this->hasTable('radio_programs')) {
            $columns = Schema::getColumnListing('radio_programs');
            $wanted = array_values(array_intersect($columns, [
                'id', 'titulo_programa', 'numero_episodio', 'archivo_mp3', 'archivo_mp3_disk',
                'delivery_status', 'radioboss_status', 'archive_org_status',
                'processing_started_at', 'processing_finished_at',
            ]));
            try {
                $this->episodes = DB::table('radio_programs')->select($wanted)->get()->map(fn ($r) => (array) $r)->all();
            } catch (Throwable $e) {
                $this->warnings[] = 'No se pudo leer radio_programs: ' . class_basename($e);
            }
        }

        if ($this->hasTable('radio_program_events') && $this->episodes !== []) {
            try {
                $episodesById = [];
                foreach ($this->episodes as $ep) {
                    if (isset($ep['id'])) {
                        $episodesById[(int) $ep['id']] = $ep;
                    }
                }
                $events = DB::table('radio_program_events')
                    ->select(['radio_program_id', 'metadata'])
                    ->whereNotNull('metadata')
                    ->get();
                foreach ($events as $ev) {
                    $meta = is_string($ev->metadata) ? json_decode($ev->metadata, true) : (array) $ev->metadata;
                    if (! is_array($meta)) {
                        continue;
                    }
                    $rawPath = ltrim(str_replace('\\', '/', (string) ($meta['raw_path'] ?? '')), '/');
                    $epId = (int) ($ev->radio_program_id ?? 0);
                    if ($rawPath !== '' && isset($episodesById[$epId])) {
                        $this->episodesByRawPath[$rawPath] = $episodesById[$epId];
                    }
                }
            } catch (Throwable) {
                // tabla opcional
            }
        }

        foreach (self::REFERENCE_TABLES as $table) {
            if (! $this->hasTable($table)) {
                continue;
            }
            $cols = array_values(array_filter(
                Schema::getColumnListing($table),
                fn (string $c) => preg_match(self::REFERENCE_COLUMN_PATTERN, $c) === 1
            ));
            if ($cols !== []) {
                $this->referenceColumns[$table] = $cols;
            }
        }

        $this->openFiles = $this->loadOpenFiles();
    }

    /**
     * Linux: rutas abiertas por procesos del mismo usuario (lectura de /proc/<pid>/fd).
     *
     * @return array<string, true>|null
     */
    private function loadOpenFiles(): ?array
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! is_dir('/proc')) {
            return null;
        }

        $open = [];
        foreach (@scandir('/proc') ?: [] as $pid) {
            if (! ctype_digit($pid)) {
                continue;
            }
            $fdDir = "/proc/{$pid}/fd";
            $fds = @scandir($fdDir);
            if ($fds === false) {
                continue;
            }
            foreach ($fds as $fd) {
                if ($fd === '.' || $fd === '..') {
                    continue;
                }
                $target = @readlink("{$fdDir}/{$fd}");
                if (is_string($target) && str_starts_with($target, '/')) {
                    $open[$target] = true;
                }
            }
        }

        return $open;
    }

    // ---------------------------------------------------------------------
    // Recorrido del sistema de archivos (sin seguir enlaces)
    // ---------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectFiles(): array
    {
        $seen = [];
        $files = [];

        foreach (self::ROOTS as $root => $role) {
            $rootPath = $this->base . '/' . $root;
            if (! is_dir($rootPath) || is_link($rootPath)) {
                if (is_link($rootPath)) {
                    $this->skippedSymlinks[] = $rootPath;
                }
                continue;
            }
            $rootReal = str_replace('\\', '/', (string) realpath($rootPath));
            $this->walk($rootPath, $rootReal, $role, $root, $seen, $files);
        }

        foreach (glob($this->base . '/' . self::ROOT_TEMP_GLOB) ?: [] as $path) {
            $path = str_replace('\\', '/', $path);
            if (is_link($path) || ! is_file($path) || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $files[] = $this->describe($path, 'temporal_id3', '');
        }

        return $files;
    }

    /**
     * @param array<string, true> $seen
     * @param array<int, array<string, mixed>> $files
     */
    private function walk(string $dir, string $rootReal, string $role, string $root, array &$seen, array &$files): void
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            $this->warnings[] = "Directorio no legible: {$dir}";

            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;

            if (is_link($path)) {
                $this->skippedSymlinks[] = $path;
                continue;
            }

            $real = str_replace('\\', '/', (string) realpath($path));
            if ($real === '' || ! str_starts_with($real . '/', rtrim($rootReal, '/') . '/')) {
                $this->skippedSymlinks[] = $path . ' (fuera de la raíz)';
                continue;
            }

            if (is_dir($path)) {
                // Una raíz más específica (declarada antes) ya se ha recorrido con su propio rol.
                $relative = substr($path, strlen($this->base) + 1);
                if ($relative !== $root && array_key_exists($relative, self::ROOTS)) {
                    continue;
                }
                $this->walk($path, $rootReal, $role, $root, $seen, $files);
                continue;
            }

            if (! is_file($path) || isset($seen[$path]) || in_array($entry, ['.gitignore', '.gitkeep'], true)) {
                continue;
            }

            $seen[$path] = true;
            $files[] = $this->describe($path, $role, $root);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(string $path, string $role, string $root): array
    {
        $relative = substr($path, strlen($this->base) + 1);
        $diskKey = str_starts_with($relative, 'public/') ? substr($relative, 7) : null;
        $mtime = (int) @filemtime($path);

        return [
            'path' => $path,
            'relative_path' => $relative,
            'disk' => $diskKey !== null ? 'public' : 'local',
            'disk_key' => $diskKey,
            'role' => $role,
            'size_bytes' => (int) @filesize($path),
            'modified_at' => $mtime > 0 ? Carbon::createFromTimestamp($mtime)->toIso8601String() : null,
            'age_hours' => $mtime > 0 ? round(($this->now->getTimestamp() - $mtime) / 3600, 1) : null,
        ];
    }

    // ---------------------------------------------------------------------
    // Clasificación
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function classify(array $file): array
    {
        $basename = basename($file['path']);
        $suffix = $this->stripOwnershipPrefix($basename);

        $isTemporaryRole = in_array($file['role'], self::TEMP_ROLES, true);

        $evidence = [
            'jobs' => $this->jobMentions([$basename, $suffix]),
            'failed_jobs' => $this->failedJobMentions([$basename, $suffix]),
            'episodes_in_flight' => $this->inFlightEpisodesFor($suffix),
            'db_references' => $isTemporaryRole ? [] : $this->databaseReferences($basename, $file['disk_key']),
            'open_by_process' => $this->openFiles === null ? null : isset($this->openFiles[$file['path']]),
            'episode' => $this->episodeForKey($file['disk_key']),
            'local_sha256' => null,
            'r2' => null,
        ];

        $item = $file + [
            'associated_record' => null,
            'r2_bucket' => $this->r2->isConfigured() ? $this->r2->bucket() : null,
            'r2_key' => null,
            'category' => self::CAT_D,
            'reason' => '',
            'evidence' => &$evidence,
        ];

        if ($evidence['episode'] !== null) {
            $item['associated_record'] = $this->episodeLabel($evidence['episode']);
        } elseif ($evidence['episodes_in_flight'] !== []) {
            $item['associated_record'] = $this->episodeLabel($evidence['episodes_in_flight'][0]);
        }

        if (in_array($file['role'], self::TEMP_ROLES, true)) {
            [$item['category'], $item['reason']] = $this->classifyTemporary($file, $evidence);
            unset($item['evidence']);
            $item['evidence'] = $evidence;

            return $item;
        }

        // Archivos persistentes: cualquier consumidor activo obliga a conservar.
        $blocking = $this->blockingConsumers($evidence);

        $r2Key = $file['disk_key'];
        if (
            $file['role'] !== 'podcast_original'
            && $evidence['episode'] !== null
            && ($evidence['episode']['archivo_mp3_disk'] ?? '') === 'r2'
        ) {
            $r2Key = (string) $evidence['episode']['archivo_mp3'];
        }
        $item['r2_key'] = $r2Key;

        $localRefs = array_values(array_filter($evidence['db_references'], fn ($r) => $r['points_to'] !== 'r2_url'));
        $episodeCurrentKey = $evidence['episode'] !== null
            ? ltrim(str_replace('\\', '/', (string) ($evidence['episode']['archivo_mp3'] ?? '')), '/')
            : '';
        $episodeUsesLocal = $evidence['episode'] !== null
            && $episodeCurrentKey === (string) $file['disk_key']
            && in_array((string) ($evidence['episode']['archivo_mp3_disk'] ?? 'public'), ['', 'public', 'local'], true);

        if ($episodeUsesLocal) {
            unset($item['evidence']);
            $item['evidence'] = $evidence;

            return [...$item, 'category' => self::CAT_C, 'reason' => 'El episodio asociado usa esta ruta local como archivo vigente (archivo_mp3_disk=public).'];
        }

        if ($blocking !== []) {
            unset($item['evidence']);
            $item['evidence'] = $evidence;

            return [...$item, 'category' => self::CAT_C, 'reason' => 'Consumidores activos: ' . implode('; ', $blocking) . '.'];
        }

        if ($localRefs !== []) {
            unset($item['evidence']);
            $item['evidence'] = $evidence;

            return [...$item, 'category' => self::CAT_C, 'reason' => 'Referenciado por la aplicación con ruta local: ' . implode(', ', array_map(fn ($r) => $r['table'] . '.' . $r['column'] . '#' . $r['id'], $localRefs)) . '.'];
        }

        if (! $this->dbAvailable) {
            unset($item['evidence']);
            $item['evidence'] = $evidence;

            return [...$item, 'category' => self::CAT_D, 'reason' => 'Base de datos no disponible; no se puede descartar que la aplicación use la ruta local ni que haya trabajos pendientes.'];
        }

        // Verificación R2
        $evidence['r2'] = $this->verifyInR2($file, (string) $r2Key, $evidence);
        [$category, $reason] = $this->decideFromR2($file, $evidence);

        if ($file['role'] === 'podcast_original' && $category !== self::CAT_A) {
            $reason .= ' Original sin procesar: el derivado que ProcessMp3Job publica en R2 (programas_procesados/…) reescribe las etiquetas ID3, por lo que nunca es idéntico byte a byte; no hay prueba de identidad y requiere decisión editorial.';
        }

        unset($item['evidence']);
        $item['evidence'] = $evidence;

        return [...$item, 'category' => $category, 'reason' => trim($reason)];
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $evidence
     * @return array{0:string,1:string}
     */
    private function classifyTemporary(array $file, array $evidence): array
    {
        if (! $this->queueInspectable) {
            return [self::CAT_D, 'La cola no es inspeccionable; no se puede descartar un trabajo en curso que use este temporal.'];
        }

        $blocking = $this->blockingConsumers($evidence);
        if ($file['age_hours'] !== null && $file['age_hours'] < $this->minAgeHours) {
            $blocking[] = "modificado hace {$file['age_hours']} h (< {$this->minAgeHours} h)";
        }
        if ($evidence['db_references'] !== []) {
            $blocking[] = 'referenciado en base de datos';
        }

        if ($blocking !== []) {
            return [self::CAT_C, 'Temporal con posibles consumidores: ' . implode('; ', $blocking) . '.'];
        }

        if (! $this->hasOwnershipPattern($file)) {
            return [self::CAT_D, 'Está en una carpeta temporal pero su nombre no sigue el patrón de propiedad del pipeline ({uuid}-nombre o chunk_N.part); procedencia no acreditada.'];
        }

        $note = $evidence['open_by_process'] === null ? ' (no se pudo comprobar descriptores abiertos en este sistema)' : '';

        return [self::CAT_B, 'Temporal propio del pipeline sin trabajos pendientes, en ejecución, diferidos ni fallidos, sin episodio en curso ni referencias, con antigüedad suficiente' . $note . '.'];
    }

    /**
     * @param array<string, mixed> $evidence
     * @return array<int, string>
     */
    private function blockingConsumers(array $evidence): array
    {
        $blocking = [];
        foreach ($evidence['jobs'] as $job) {
            $blocking[] = "trabajo #{$job['id']} ({$job['state']}, cola {$job['queue']})";
        }
        foreach ($evidence['failed_jobs'] as $job) {
            $blocking[] = "trabajo fallido reintentable #{$job['id']}";
        }
        foreach ($evidence['episodes_in_flight'] as $episode) {
            $blocking[] = 'episodio en curso ' . $this->episodeLabel($episode);
        }
        if ($evidence['open_by_process'] === true) {
            $blocking[] = 'abierto por un proceso';
        }

        return $blocking;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $evidence
     * @return array<string, mixed>
     */
    private function verifyInR2(array $file, string $key, array &$evidence): array
    {
        $r2 = ['key' => $key, 'head' => null, 'size_match' => null, 'etag_kind' => null, 'remote_sha256' => null, 'sha256_match' => null, 'app_url_is_r2' => null];

        if ($key === '' || ! $this->r2->isConfigured()) {
            $r2['head'] = ['status' => $key === '' ? 'sin_clave' : 'not_configured'];

            return $r2;
        }

        $head = $this->r2->head($key);
        $r2['head'] = $head;

        if ($head['status'] !== 'present') {
            return $r2;
        }

        $r2['size_match'] = $head['size'] === $file['size_bytes'];
        $etag = (string) ($head['etag'] ?? '');
        $r2['etag_kind'] = preg_match('/^[a-f0-9]{32}$/i', $etag) === 1
            ? 'hex32_posible_md5_no_asumido'
            : (str_contains($etag, '-') ? 'multipart_no_md5' : 'desconocido');

        $appUrl = $this->r2->appUrl($key);
        $base = $this->r2->publicBaseUrl();
        $r2['app_url_is_r2'] = $base !== '' && str_starts_with($appUrl, $base . '/');

        if ($this->deepVerify && $r2['size_match']) {
            $evidence['local_sha256'] = @hash_file('sha256', $file['path']) ?: null;
            $remote = $this->r2->hash($key);
            $r2['remote_sha256'] = $remote['sha256'];
            $r2['remote_bytes'] = $remote['bytes'];
            $r2['hash_status'] = $remote['status'];
            $r2['sha256_match'] = $remote['status'] === 'ok' && $evidence['local_sha256'] !== null
                && hash_equals($evidence['local_sha256'], (string) $remote['sha256']);
        }

        return $r2;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $evidence
     * @return array{0:string,1:string}
     */
    private function decideFromR2(array $file, array $evidence): array
    {
        $r2 = $evidence['r2'];
        $status = $r2['head']['status'] ?? 'error';

        return match (true) {
            $status === 'not_configured' => [self::CAT_D, 'R2 no está configurado en este entorno; no se puede verificar la copia definitiva.'],
            $status === 'sin_clave' => [self::CAT_D, 'Archivo fuera del disco público; no hay clave R2 deducible.'],
            $status === 'missing' => [self::CAT_D, "No existe objeto en R2 con la clave «{$r2['key']}»; sin copia definitiva comprobada."],
            $status !== 'present' => [self::CAT_D, 'Error al consultar R2 (' . ($r2['head']['error'] ?? 'desconocido') . '); estado no verificable.'],
            $r2['size_match'] === false => [self::CAT_D, 'El objeto R2 existe pero su tamaño difiere del archivo local; no son la misma copia.'],
            ! $this->deepVerify => [self::CAT_D, 'Objeto R2 presente con el mismo tamaño; pendiente de checksum (ejecutar con --deep-verify). Tamaño igual no demuestra identidad.'],
            $r2['sha256_match'] !== true => [self::CAT_D, 'No se pudo confirmar el SHA-256 del objeto R2 frente al archivo local (' . ($r2['hash_status'] ?? 'sin hash') . ').'],
            $r2['app_url_is_r2'] !== true => [self::CAT_D, 'SHA-256 coincide, pero la URL que genera la aplicación no apunta a R2; no se acredita que la web pueda usar el objeto.'],
            default => [self::CAT_A, 'SHA-256 local = SHA-256 del objeto R2 descargado en streaming; la aplicación genera URL de R2 y no quedan referencias locales ni trabajos pendientes.'],
        };
    }

    // ---------------------------------------------------------------------
    // Búsquedas
    // ---------------------------------------------------------------------

    /**
     * @param array<int, string> $needles
     * @return array<int, array<string, mixed>>
     */
    private function jobMentions(array $needles): array
    {
        $out = [];
        foreach ($this->jobs as $job) {
            if (! $this->payloadMentions((string) $job['payload'], $needles)) {
                continue;
            }
            $state = 'pendiente';
            if (! empty($job['reserved_at'])) {
                $state = 'en_ejecucion';
            } elseif ((int) $job['available_at'] > $this->now->getTimestamp()) {
                $state = 'diferido_reintento';
            } elseif ((int) $job['attempts'] > 0) {
                $state = 'reintento_pendiente';
            }
            $out[] = ['id' => $job['id'], 'queue' => $job['queue'], 'state' => $state, 'attempts' => (int) $job['attempts']];
        }

        return $out;
    }

    /**
     * @param array<int, string> $needles
     * @return array<int, array<string, mixed>>
     */
    private function failedJobMentions(array $needles): array
    {
        $out = [];
        foreach ($this->failedJobs as $job) {
            if ($this->payloadMentions((string) $job['payload'], $needles)) {
                $out[] = ['id' => $job['id'], 'failed_at' => $job['failed_at']];
            }
        }

        return $out;
    }

    /**
     * Los payloads de Laravel serializan objetos; las rutas pueden aparecer con
     * barras escapadas. Se compara contra el texto tal cual y des-escapado.
     *
     * @param array<int, string> $needles
     */
    private function payloadMentions(string $payload, array $needles): bool
    {
        $variants = [$payload, stripslashes($payload), str_replace('\\/', '/', $payload)];
        foreach (array_unique(array_filter($needles, fn ($n) => strlen($n) >= 6)) as $needle) {
            foreach ($variants as $haystack) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inFlightEpisodesFor(string $basename): array
    {
        $out = [];
        foreach ($this->episodes as $episode) {
            if (! $this->episodeInFlight($episode)) {
                continue;
            }
            $episodeFile = basename(str_replace('\\', '/', (string) ($episode['archivo_mp3'] ?? '')));
            if ($episodeFile !== '' && ($episodeFile === $basename || $this->stripOwnershipPrefix($basename) === $episodeFile)) {
                $out[] = $episode;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $episode
     */
    private function episodeInFlight(array $episode): bool
    {
        if (! empty($episode['processing_started_at']) && empty($episode['processing_finished_at'])) {
            return true;
        }
        foreach (['delivery_status', 'radioboss_status', 'archive_org_status'] as $col) {
            $value = strtolower((string) ($episode[$col] ?? ''));
            foreach (['pending', 'uploading', 'processing', 'partial', 'retry'] as $marker) {
                if ($value !== '' && str_contains($value, $marker)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function episodeForKey(?string $diskKey): ?array
    {
        if ($diskKey === null || $diskKey === '') {
            return null;
        }
        foreach ($this->episodes as $episode) {
            $key = ltrim(str_replace('\\', '/', (string) ($episode['archivo_mp3'] ?? '')), '/');
            if ($key !== '' && $key === $diskKey) {
                return $episode;
            }
        }

        return $this->episodesByRawPath[$diskKey] ?? null;
    }

    /**
     * @return array<int, array{table:string,column:string,id:mixed,points_to:string}>
     */
    private function databaseReferences(string $basename, ?string $diskKey): array
    {
        if (strlen($basename) < 6) {
            return [];
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $basename);
        $r2Base = $this->r2->publicBaseUrl();
        $refs = [];

        foreach ($this->referenceColumns as $table => $columns) {
            if ($columns === []) {
                continue;
            }
            $grammar = DB::getQueryGrammar();
            $selectCols = array_values(array_unique(array_merge(
                ['id'],
                $columns,
                $table === 'radio_programs' && in_array('archivo_mp3', $columns, true) ? ['archivo_mp3_disk'] : []
            )));

            try {
                $rows = DB::table($table)
                    ->select($selectCols)
                    ->where(function ($query) use ($columns, $grammar, $escaped): void {
                        foreach ($columns as $column) {
                            $query->orWhereRaw($grammar->wrap($column) . " LIKE ? ESCAPE '!'", ['%' . $escaped . '%']);
                        }
                    })
                    ->limit(25)
                    ->get();
            } catch (Throwable) {
                continue;
            }

            foreach ($rows as $row) {
                $row = (array) $row;
                foreach ($columns as $column) {
                    $value = (string) ($row[$column] ?? '');
                    if ($value === '' || ! str_contains($value, $basename)) {
                        continue;
                    }
                    $pointsTo = 'desconocido';
                    if ($table === 'radio_programs' && $column === 'archivo_mp3') {
                        $rowDisk = strtolower(trim((string) ($row['archivo_mp3_disk'] ?? 'public')));
                        if ($rowDisk === 'r2') {
                            $pointsTo = 'r2_url';
                        } elseif (in_array($rowDisk, ['', 'public', 'local'], true)) {
                            $pointsTo = 'local';
                        }
                    } elseif ($r2Base !== '' && str_contains($value, $r2Base)) {
                        $pointsTo = 'r2_url';
                    } elseif ($diskKey !== null && (str_contains($value, '/storage/' . $diskKey) || trim($value) === $diskKey)) {
                        $pointsTo = 'local';
                    }
                    $refs[] = ['table' => $table, 'column' => $column, 'id' => $row['id'] ?? null, 'points_to' => $pointsTo];
                }
            }
        }

        return $refs;
    }

    // ---------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------

    private function stripOwnershipPrefix(string $basename): string
    {
        return (string) preg_replace('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}-/i', '', $basename);
    }

    /**
     * @param array<string, mixed> $file
     */
    private function hasOwnershipPattern(array $file): bool
    {
        $basename = basename($file['path']);

        return match ($file['role']) {
            'temporal_subida_fragmentada' => preg_match('/^chunk_\d+\.part$/', $basename) === 1,
            'temporal_id3' => preg_match('/^temp_id3_[A-Za-z0-9]{10}\.mp3$/', $basename) === 1,
            default => $this->stripOwnershipPrefix($basename) !== $basename,
        };
    }

    /**
     * @param array<string, mixed> $episode
     */
    private function episodeLabel(array $episode): string
    {
        $title = trim((string) ($episode['titulo_programa'] ?? ''));
        $number = $episode['numero_episodio'] ?? null;

        return 'radio_programs#' . ($episode['id'] ?? '?') . ($title !== '' ? " ({$title}" . ($number ? " #{$number}" : '') . ')' : '');
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function summarize(array $items): array
    {
        $summary = [];
        foreach ([self::CAT_A, self::CAT_B, self::CAT_C, self::CAT_D] as $cat) {
            $subset = array_filter($items, fn ($i) => $i['category'] === $cat);
            $bytes = array_sum(array_map(fn ($i) => (int) $i['size_bytes'], $subset));
            $summary[$cat] = ['files' => count($subset), 'bytes' => $bytes, 'mb' => round($bytes / 1048576, 2)];
        }
        $recoverable = $summary[self::CAT_A]['bytes'] + $summary[self::CAT_B]['bytes'];
        $summary['recuperable_verificado'] = ['bytes' => $recoverable, 'mb' => round($recoverable / 1048576, 2)];
        $total = array_sum(array_map(fn ($i) => (int) $i['size_bytes'], $items));
        $summary['total'] = ['files' => count($items), 'bytes' => $total, 'mb' => round($total / 1048576, 2)];

        return $summary;
    }
}

