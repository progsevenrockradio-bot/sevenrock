# Flujo de subida de podcasts

Este documento agrupa la lógica principal del flujo de subida de MP3 en la nueva web:

1. validación en el formulario
2. subida del MP3 a la app
3. procesado del archivo
4. envío a RadioBOSS
5. sincronización con Archive.org
6. envío de correo de notificación

## Archivos involucrados

- [app/Http/Controllers/Admin/PodcastUploadController.php](../app/Http/Controllers/Admin/PodcastUploadController.php)
- [app/Jobs/UploadMp3Job.php](../app/Jobs/UploadMp3Job.php)
- [app/Services/ArchiveOrgPodcastService.php](../app/Services/ArchiveOrgPodcastService.php)
- [resources/views/admin/podcast-uploads/index.blade.php](../resources/views/admin/podcast-uploads/index.blade.php)
- [resources/js/app.js](../resources/js/app.js)
- [config/filesystems.php](../config/filesystems.php)
- [config/services.php](../config/services.php)
- [tests/Feature/AdminPodcastUploadTest.php](../tests/Feature/AdminPodcastUploadTest.php)

## 1. Formulario y prevalidación

Archivo: `resources/views/admin/podcast-uploads/index.blade.php`

### Qué hace

- Permite elegir el programa maestro por día.
- Captura el título del episodio, fecha, invitado, reseña, imagen y MP3.
- Muestra barra de progreso, ETA y etiquetas de estado.
- Presenta errores de validación sin confundirlos con fallos de subida.

### Puntos clave

```blade
<form
    action="{{ route('admin.podcast-uploads.store') }}"
    method="POST"
    enctype="multipart/form-data"
    x-data="podcastUploadForm({ initialDay: '{{ $initialDay }}' })"
    @submit="submit($event)"
>
```

```blade
<div class="mt-1 flex items-center justify-between text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
    <span x-text="uploading ? 'Subiendo' : (statusMessage || 'Listo para subir')"></span>
    <span x-text="progressLabel"></span>
</div>
<div class="mt-1 flex items-center justify-between text-[10px] uppercase tracking-[.18em] text-[#5f5f5f]">
    <span x-text="fileSizeLabel ? `Peso: ${fileSizeLabel}` : ''"></span>
    <span x-text="uploadEtaLabel"></span>
</div>
```

## 2. Lógica del cliente

Archivo: `resources/js/app.js`

### Qué hace

- Valida el formulario antes de enviar.
- Calcula una ETA aproximada basada en el tamaño del MP3.
- Actualiza progreso real de subida.
- Cambia de fase: `Validando`, `Subiendo`, `Procesando RadioBOSS`, `Sincronizando Archive.org`, `Preparando descarga`, `Error`.

### Puntos clave

```js
Alpine.data('podcastUploadForm', (options = {}) => ({
    activeDay: options.initialDay ?? 'LUNES',
    uploading: false,
    progress: 0,
    progressLabel: '0%',
    statusMessage: '',
    phaseLabel: 'Listo',
    phaseDetailLabel: '',
    errorMessages: [],
    uploadEtaLabel: '',
    fileSizeLabel: '',
```

```js
validateBeforeSubmit(form) {
    const errors = [];
    const masterProgram = form.querySelector(`[data-day-panel="${this.activeDay}"] select[name="master_program_id"]`);
    const title = form.querySelector('[name="live_title"]');
    const date = form.querySelector('[name="fecha_emision"]');
    const audioInput = form.querySelector('[name="archivo_mp3"]');
    const audioFile = audioInput?.files?.[0] ?? null;

    // valida programa, título, fecha y MP3 antes de iniciar la subida
}
```

```js
xhr.upload.onprogress = (progressEvent) => {
    const percent = Math.min(100, Math.max(0, Math.round((progressEvent.loaded / progressEvent.total) * 100)));
    this.progress = percent;
    this.progressLabel = `${percent}%`;
};
```

## 3. Controlador administrativo

Archivo: `app/Http/Controllers/Admin/PodcastUploadController.php`

### Qué hace

- Valida el input.
- Guarda el archivo en el disco público.
- Crea el registro `radio_programs`.
- Dispara el job de procesamiento.

### Puntos clave

```php
$data = $request->validate([
    'master_program_id' => ['required', 'integer', 'exists:master_programs,id'],
    'numero_episodio' => ['nullable', 'integer', 'min:1'],
    'live_title' => ['required', 'string', 'max:255'],
    'fecha_emision' => ['required', 'date'],
    'archivo_mp3' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp3', 'max:512000'],
], [
    'live_title.required' => 'El título del episodio es obligatorio.',
    'archivo_mp3.required' => 'Debes seleccionar un archivo MP3.',
]);
```

```php
UploadMp3Job::dispatchAfterResponse(
    $radioProgram->fresh(['masterProgram']) ?? $radioProgram,
    (string) $radioProgram->archivo_mp3,
    $downloadProcessedMp3,
);
```

## 4. Job de procesamiento

Archivo: `app/Jobs/UploadMp3Job.php`

### Qué hace

- Reescribe nombre y metadatos del MP3.
- Guarda copia local procesada.
- Sube a RadioBOSS por FTP.
- Sincroniza a Archive.org si corresponde.
- Envía correo de notificación.
- Limpia el archivo local si no se pidió conservar copia.

### Puntos clave

```php
$fileName = basename($nuevaRuta);
$remotePath = $folder . '/' . $fileName;
$ftpHost = trim((string) config('filesystems.disks.radioboss.host', ''));
$archiveShouldSync = (bool) ($this->radioProgram->sync_archive_org ?? true);
```

```php
if ($ftpHost !== '') {
    $uploadOk = $this->uploadToRadiobossWithRetries($folder, $remotePath, $nuevaRuta);
    RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
        'enviado_radioboss' => $uploadOk,
    ]));
}
```

```php
if ($archiveShouldSync && $archiveOrgPodcastService->canSync()) {
    $archiveOrgPodcastService->syncEpisode($this->radioProgram->fresh(['masterProgram']) ?? $this->radioProgram);
}
```

```php
Mail::mailer($this->resolveNotificationMailer())
    ->to($emailDestino)
    ->send(new ProgramUploadedNotification($fileName, $uploadOk));
```

### Subida FTP

```php
protected function uploadToRadioboss(string $folder, string $remotePath, string $localPath): void
{
    $disk = Storage::disk('radioboss');

    if (filter_var(config('filesystems.disks.radioboss.clear_before_upload', false), FILTER_VALIDATE_BOOL)) {
        $this->clearRemoteFileBeforeUpload($disk, $folder, $remotePath);
    }

    $stream = Storage::disk('public')->readStream($localPath);
    $uploaded = $disk->writeStream($remotePath, $stream);
}
```

## 5. Servicio de Archive.org

Archivo: `app/Services/ArchiveOrgPodcastService.php`

### Qué hace

- Determina si puede sincronizar.
- Resuelve el identifier.
- Sube el MP3 a Archive.org.
- Aplica metadatos al item.

### Puntos clave

```php
public function canSync(): bool
{
    return trim((string) config('services.archive_org.access_key', '')) !== ''
        && trim((string) config('services.archive_org.secret_key', '')) !== '';
}
```

```php
public function resolveIdentifier(RadioProgram $episode, ?MasterProgram $master = null): string
{
    $configured = trim((string) ($master?->archive_identifier ?? ''));
    if ($configured !== '') {
        return $configured;
    }

    $base = trim((string) ($master?->nombre ?? $episode->titulo_programa ?? 'podcast'));
    $slug = Str::slug($base, '-');
    $suffix = max(1, (int) ($master?->id ?? $episode->id));

    return ($slug !== '' ? $slug : 'podcast') . '-' . $suffix;
}
```

## 6. Configuración

### `config/filesystems.php`

```php
'radioboss' => [
    'driver' => 'ftp',
    'host' => env('RADIOBOSS_FTP_SERVER', env('RADIOBOSS_FTP_HOST', '')),
    'username' => env('RADIOBOSS_FTP_USER', env('RADIOBOSS_FTP_USERNAME', '')),
    'password' => env('RADIOBOSS_FTP_PASS', env('RADIOBOSS_FTP_PASSWORD', '')),
    'clear_before_upload' => filter_var(env('RADIOBOSS_FTP_CLEAR_BEFORE_UPLOAD', false), FILTER_VALIDATE_BOOL),
    'verify_after_upload' => filter_var(env('RADIOBOSS_FTP_VERIFY_AFTER_UPLOAD', false), FILTER_VALIDATE_BOOL),
],
```

### `config/services.php`

```php
'archive_org' => [
    'access_key' => env('ARCHIVE_ORG_ACCESS_KEY'),
    'secret_key' => env('ARCHIVE_ORG_SECRET_KEY'),
    'region' => env('ARCHIVE_REGION', 'us-east-1'),
    'bucket' => env('ARCHIVE_BUCKET'),
    'endpoint' => env('ARCHIVE_ENDPOINT', 'https://s3.us.archive.org'),
],
```

## 7. Verificación local y smoke test

El flujo cuenta con una batería de pruebas funcionales en:

- [tests/Feature/AdminPodcastUploadTest.php](../tests/Feature/AdminPodcastUploadTest.php)

### Qué cubre

- subida de podcast desde el panel admin
- asignación manual y automática del episodio
- preservación de copia local cuando se solicita descarga
- renderizado de la vista principal por día
- renderizado del fragmento de últimos episodios
- atributos de estado usados por el polling inteligente

### Smoke test relevante

El caso `test_smoke_upload_pipeline_renders_recent_fragment_with_status_attributes()` valida el camino completo mínimo:

1. crea un programa maestro
2. sube un MP3 como administrador
3. verifica que el episodio exista
4. consulta `admin.podcast-uploads.recent`
5. comprueba que el fragmento expone:
   - `data-status="partial"`
   - `data-podcast-refresh-active="0"`
   - el título del episodio

### Comando local

```bash
C:\laragon\bin\php\php-8.4.20-Win32-vs17-x64\php.exe artisan test --filter=AdminPodcastUploadTest
```

### Resultado esperado

- todos los tests del flujo pasan
- el fragmento HTML refleja el estado real de los episodios
- el polling inteligente puede decidir si sigue activo o se detiene

## 8. Notas de operación

Para que el pipeline funcione en entornos de desarrollo y producción:

- debe existir un worker de colas activo
- el navegador solo muestra el estado y refresca el fragmento
- la ejecución real ocurre en background mediante jobs

## 7. Tests

Archivo: `tests/Feature/AdminPodcastUploadTest.php`

### Qué cubre

- subida normal
- capítulo manual
- correlativo automático
- preservación de copia local
- agrupación por día
- día actual abierto por defecto

## 8. Orden real del flujo

1. El usuario completa el formulario.
2. El cliente valida antes de subir.
3. Laravel guarda el MP3 en local.
4. Se crea el episodio en `radio_programs`.
5. Se dispara `UploadMp3Job` después de la respuesta.
6. El job escribe tags ID3.
7. El job sube a RadioBOSS.
8. Si corresponde, sincroniza Archive.org.
9. Se envía el correo.
10. Se limpia el archivo local si no se pidió conservar copia.

## 9. Nota operativa

La carga al navegador es una cosa y el procesamiento posterior es otra. La barra y la ETA reflejan la transferencia del archivo al servidor. RadioBOSS y Archive.org quedan en la fase de procesamiento.
