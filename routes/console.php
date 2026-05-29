<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Support\ArchiveIdentifierAudit;
use App\Models\Post;
use App\Jobs\UploadMp3Job;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\Talent;
use App\Models\TalentSubscription;
use App\Mail\SubscriptionExpiredMail;
use App\Mail\SubscriptionRenewalMail;
use App\Services\FeaturedTalentService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use App\Support\PublicMediaUrl;
use App\Support\WordPressContent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sevenrock:audit-archive-identifiers {--limit=200}', function () {
    $report = app(ArchiveIdentifierAudit::class)->buildReport((int) $this->option('limit'));

    $summary = $report['summary'];
    $this->info(sprintf(
        'Checked %d master programs and %d radio programs.',
        $summary['master_programs_checked'],
        $summary['radio_programs_checked']
    ));

    if (($summary['issues'] ?? 0) === 0) {
        $this->info('No archive identifier issues detected.');

        return 0;
    }

    $this->warn(sprintf('Found %d potential issue(s).', $summary['issues']));

    if ($report['master_programs'] !== []) {
        $this->line('');
        $this->line('Master programs');
        $this->table(
            ['ID', 'Name', 'Identifier', 'Issues', 'Status'],
            array_map(static fn (array $row): array => [
                $row['id'],
                $row['name'],
                $row['identifier'],
                implode(', ', $row['issues']),
                $row['status'] ?? '',
            ], $report['master_programs'])
        );
    }

    if ($report['radio_programs'] !== []) {
        $this->line('');
        $this->line('Radio programs');
        $this->table(
            ['ID', 'Title', 'Identifier', 'Remote path', 'Issues', 'Status'],
            array_map(static fn (array $row): array => [
                $row['id'],
                $row['name'],
                $row['identifier'],
                $row['remote_path'] ?? '',
                implode(', ', $row['issues']),
                $row['status'] ?? '',
            ], $report['radio_programs'])
        );
    }

    return 1;
})->purpose('Audit Archive.org identifiers and podcast metadata');

Artisan::command('radioboss:test {--folder= : Carpeta remota destino (relativa al root del disco)} {--ext=mp3 : Extension del archivo de prueba} {--keep : No borra el archivo de prueba}', function () {
    $diskConfig = (array) config('filesystems.disks.radioboss', []);
    $host = trim((string) ($diskConfig['host'] ?? ''));
    $user = trim((string) ($diskConfig['username'] ?? ''));
    $root = (string) ($diskConfig['root'] ?? '/');
    $ssl = (bool) ($diskConfig['ssl'] ?? false);
    $passive = (bool) ($diskConfig['passive'] ?? true);
    $timeout = (int) ($diskConfig['timeout'] ?? 60);

    if ($host === '' || $user === '') {
        $this->error('Faltan credenciales. Configura RADIOBOSS_FTP_SERVER / RADIOBOSS_FTP_USER / RADIOBOSS_FTP_PASS en .env');

        return 1;
    }

    $this->line('RadioBOSS FTP test');
    $this->line('Host: ' . $host);
    $this->line('Root: ' . $root);
    $this->line('SSL (FTPS): ' . ($ssl ? 'true' : 'false'));
    $this->line('Passive: ' . ($passive ? 'true' : 'false'));
    $this->line('Timeout: ' . $timeout . 's');

    if (! $ssl) {
        $this->warn('Nota: si RadioBOSS exige TLS explicito, este test puede fallar hasta activar RADIOBOSS_FTP_SSL=true.');
    }

    $folderOption = trim((string) $this->option('folder'));
    $folder = $folderOption !== '' ? $folderOption : '__srr_ftp_test';
    $folder = str_replace('\\', '/', $folder);
    $folder = trim($folder, " \t\n\r\0\x0B/");
    $folder = str_replace('..', '', $folder);
    $folder = preg_replace('/[^A-Za-z0-9_\-\/]/', '', $folder) ?: '__srr_ftp_test';
    $folder = trim($folder, '/');
    $folder = $folder === '' ? '__srr_ftp_test' : $folder;

    $ext = trim((string) $this->option('ext')) ?: 'mp3';
    $ext = preg_replace('/[^A-Za-z0-9]/', '', $ext) ?: 'mp3';
    $fileName = '__srr_test_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(6)) . '.' . Str::lower($ext);
    $remotePath = $folder . '/' . $fileName;
    $payload = "ID3" . Str::random(128) . "\n";

    try {
        $disk = Storage::disk('radioboss');

        if (method_exists($disk, 'createDirectory')) {
            $disk->createDirectory($folder);
        }

        $this->line('Listando carpeta: ' . $folder);
        $files = $disk->files($folder);
        $this->line('OK. Archivos: ' . count($files));

        $this->line('Escribiendo: ' . $remotePath);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $payload);
        rewind($stream);
        $writeOk = $disk->writeStream($remotePath, $stream);
        fclose($stream);

        if ($writeOk === false) {
            $this->error('Fallo en writeStream() (retorno false).');

            return 1;
        }

        $this->line('Verificando exists(): ' . $remotePath);
        if (! $disk->exists($remotePath)) {
            $this->error('El archivo no aparece en exists(); revisa permisos/ruta/root.');

            return 1;
        }

        $this->line('Leyendo archivo de prueba...');
        $read = $disk->get($remotePath);
        if (! is_string($read) || $read === '') {
            $this->error('No se pudo leer el archivo (get() vacio/no string).');

            return 1;
        }

        if (! $this->option('keep')) {
            $this->line('Borrando archivo de prueba...');
            $disk->delete($remotePath);
            $this->line('OK. Borrado.');
        } else {
            $this->warn('keep=true: archivo dejado en remoto: ' . $remotePath);
        }

        $this->info('SUCCESS: Conexion y permisos OK.');

        return 0;
    } catch (\Throwable $e) {
        $this->error('FAIL: ' . $e->getMessage());
        $this->line('Tip: revisa RADIOBOSS_FTP_ROOT, permisos del usuario y RADIOBOSS_FTP_SSL/passive/timeout.');

        return 1;
    }
})->purpose('Prueba la conexion FTP a RadioBOSS');

Artisan::command('sevenrock:resolve-post-media {slug : Slug del post a inspeccionar}', function (string $slug) {
    $post = Post::query()->where('slug', $slug)->first();

    if (! $post) {
        $this->error('No existe un post con slug: ' . $slug);

        return 1;
    }

    $this->info('Post: ' . $post->title);
    $this->line('Slug: ' . $post->slug);
    $this->line('Featured image raw: ' . (string) ($post->featured_image ?? $post->featured_image_path ?? ''));
    $this->line('Featured image resolved: ' . $post->featured_image_url);

    $blocks = WordPressContent::toRenderableBlocks($post->content ?? []);
    $images = [];

    foreach ($blocks as $index => $blockHtml) {
        if (! is_string($blockHtml) || ! str_contains($blockHtml, '<img')) {
            continue;
        }

        if (! preg_match_all('/<img\b[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/i', $blockHtml, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $rawSrc = html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5);
            $alt = html_entity_decode($match[2] ?? '', ENT_QUOTES | ENT_HTML5);
            $resolved = PublicMediaUrl::normalizePublicUrl($rawSrc);
            $images[] = [
                'block' => $index + 1,
                'alt' => $alt,
                'raw' => $rawSrc,
                'resolved' => $resolved !== '' ? $resolved : '(no resuelto)',
            ];
        }
    }

    if ($images === []) {
        $this->warn('No se encontraron imágenes dentro del contenido renderizable.');

        return 0;
    }

    $this->table(
        ['Bloque', 'ALT', 'Raw src', 'Resolved URL'],
        array_map(static fn (array $row): array => [
            $row['block'],
            $row['alt'],
            $row['raw'],
            $row['resolved'],
        ], $images)
    );

    return 0;
})->purpose('Inspecciona la resolucion de imagenes legacy de un post');

Artisan::command('sevenrock:test-upload {--file= : Ruta relativa en storage/app/public del MP3 local} {--master-name=Seven Rock Test : Nombre del programa maestro temporal} {--folder=Programas : Carpeta FTP remota} {--archive=0 : 1 para sincronizar con Archive.org, 0 para omitirlo} {--keep-local=1 : 1 para conservar la copia local procesada}', function () {
    $storedPath = trim((string) $this->option('file'));

    if ($storedPath === '') {
        $this->error('Debes indicar --file=podcast-inbox/test/test.mp3');

        return 1;
    }

    if (! Storage::disk('public')->exists($storedPath)) {
        $this->error('No existe el MP3 local en storage/app/public: ' . $storedPath);

        return 1;
    }

    $masterName = trim((string) $this->option('master-name')) ?: 'Seven Rock Test';
    $folder = trim((string) $this->option('folder')) ?: 'Programas';
    $syncArchive = filter_var($this->option('archive'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    $syncArchive = $syncArchive ?? false;
    $keepLocalCopy = filter_var($this->option('keep-local'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    $keepLocalCopy = $keepLocalCopy ?? true;

    $master = MasterProgram::query()->firstOrCreate(
        ['nombre' => $masterName],
        [
            'conductor' => 'Seven Rock Test',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '17:00:00',
            'genero' => 'Metal',
            'ruta_ftp' => $folder,
            'activo' => true,
        ],
    );

    $episodeNumber = max(1, ((int) RadioProgram::query()->where('master_program_id', $master->id)->max('numero_episodio')) + 1);
    $radioProgram = RadioProgram::query()->create([
        'master_program_id' => $master->id,
        'titulo_programa' => (string) $master->nombre,
        'conductor' => (string) $master->conductor,
        'numero_episodio' => $episodeNumber,
        'fecha_emision' => Carbon::now()->toDateString(),
        'biografia_invitado' => 'TEST',
        'resena' => 'Upload test generado desde artisan.',
        'live_title' => 'Upload test ' . now()->format('Y-m-d H:i:s'),
        'live_description' => 'Upload test generado desde artisan.',
        'comentario_episodio' => 'Upload test generado desde artisan.',
        'archivo_mp3' => $storedPath,
        'enviado_radioboss' => false,
        'sync_archive_org' => $syncArchive,
        'caratula_programa' => null,
        'ruta_ftp_radioboss' => $folder,
        'dia_transmision' => (string) $master->dia_transmision,
        'genero_musical' => (string) $master->genero,
        'email_notificacion' => null,
    ]);

    $previousMailer = config('services.notifications.mailer');
    config(['services.notifications.mailer' => 'log']);

    try {
        UploadMp3Job::dispatchSync($radioProgram->fresh(['masterProgram']) ?? $radioProgram, $storedPath, $keepLocalCopy);
    } catch (\Throwable $exception) {
        $radioProgram->refresh();
        $this->error('El upload de prueba falló: ' . $exception->getMessage());
        $this->line('RadioProgram ID: ' . $radioProgram->id);
        $this->line('archivo_mp3: ' . (string) $radioProgram->archivo_mp3);
        $this->line('enviado_radioboss: ' . var_export($radioProgram->enviado_radioboss, true));
        $this->line('archive_org_status: ' . (string) $radioProgram->archive_org_status);
        $this->line('delivery_status: ' . (string) ($radioProgram->delivery_status ?? ''));

        config(['services.notifications.mailer' => $previousMailer]);

        return 1;
    }

    $radioProgram->refresh();
    $this->info('Upload de prueba completado.');
    $this->line('RadioProgram ID: ' . $radioProgram->id);
    $this->line('archivo_mp3: ' . (string) $radioProgram->archivo_mp3);
    $this->line('enviado_radioboss: ' . var_export($radioProgram->enviado_radioboss, true));
    $this->line('radioboss_status: ' . (string) ($radioProgram->radioboss_status ?? ''));
    $this->line('archive_org_status: ' . (string) $radioProgram->archive_org_status);
    $this->line('delivery_status: ' . (string) ($radioProgram->delivery_status ?? ''));
    $this->line('delivery_last_error: ' . (string) ($radioProgram->delivery_last_error ?? ''));
    $this->line('delivery_metadata: ' . json_encode($radioProgram->delivery_metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    config(['services.notifications.mailer' => $previousMailer]);

    return 0;
})->purpose('Ejecuta un upload temporal real y muestra el estado guardado en radio_programs');

Artisan::command('talents:refresh-featured {--limit=6}', function () {
    $limit = max(1, (int) $this->option('limit'));
    $featuredIds = app(FeaturedTalentService::class)->getFeatured($limit)->pluck('id')->all();

    Talent::query()->update(['is_featured' => false]);

    if ($featuredIds !== []) {
        Talent::query()->whereIn('id', $featuredIds)->update(['is_featured' => true]);
    }

    $this->info('Featured talents refreshed: ' . count($featuredIds));

    return 0;
})->purpose('Refresh featured talents based on recent interactions');

// Process queue every minute

Schedule::command('queue:work', ['--stop-when-empty', '--timeout=120'])->everyMinute()->withoutOverlapping();

// Publish scheduled posts every minute
Schedule::command('posts:publish-scheduled')->everyMinute();
