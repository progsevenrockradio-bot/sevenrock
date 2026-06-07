# Proceso técnico de subida de podcasts

## 1. Objetivo

Este documento describe el flujo técnico del módulo de subida de podcasts en Seven Rock Radio.

El objetivo operativo es mantener un pipeline que:

1. procese el MP3 localmente,
2. suba el episodio a RadioBOSS,
3. suba el episodio a Archive.org,
4. notifique cada etapa por separado,
5. deje trazabilidad de estado, tiempos, errores y reintentos.

La separación por etapas es importante porque el proceso completo puede tardar varios minutos o quedarse bloqueado en un punto intermedio. Con notificaciones independientes, el operador sabe qué fase terminó y cuál no.

## 2. Alcance

Este documento cubre:

- la entrada desde el panel administrativo,
- los jobs y eventos del pipeline,
- el modelo de estados persistidos,
- la auditoría de eventos,
- los correos por etapa,
- los reintentos selectivos,
- el comando de reconciliación,
- la observabilidad del panel.

No cubre:

- la infraestructura física del servidor,
- la configuración SMTP/FTP/Archive.org,
- el diseño visual del formulario,
- procesos manuales fuera del módulo de podcasts.

## 3. Resumen ejecutivo

El pipeline actual se divide en cuatro bloques:

1. Procesado local del MP3.
2. Subida a RadioBOSS.
3. Subida a Archive.org.
4. Verificación final de entrega.

El cambio más importante es que ya no existe un único correo al final. Ahora hay un correo por cada destino:

- RadioBOSS confirmado,
- Archive.org confirmado.

Si Archive.org queda en indexación pendiente, eso se refleja como estado propio sin bloquear la confirmación de RadioBOSS.

## 4. Flujo funcional general

```text
Formulario admin
   ↓
PodcastUploadController
   ↓
radio_programs + archivo MP3 local
   ↓
ProcessMp3Job
   ↓
PodcastProcessed event
   ↓
DispatchPodcastDistributionJobs listener
   ↓
UploadRadiobossJob        UploadArchiveOrgJob
   ↓                           ↓
PodcastRadiobossUploaded   PodcastArchiveUploaded
   ↓                           ↓
SendPodcastRadiobossNotification
SendPodcastArchiveNotification
   ↓                           ↓
PodcastDeliveryVerified event cuando ambas fases están listas
   ↓
FinalizePodcastDelivery listener
```

## 5. Componentes involucrados

### 5.1 Controlador de subida

Archivo:

- [app/Http/Controllers/Admin/PodcastUploadController.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Http/Controllers/Admin/PodcastUploadController.php)

Responsabilidades:

- validar la entrada,
- guardar el archivo original,
- crear el registro del episodio,
- inicializar los estados del pipeline,
- disparar el procesado,
- permitir reintentos selectivos.

### 5.2 Job de procesado local

Archivo:

- [app/Jobs/ProcessMp3Job.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Jobs/ProcessMp3Job.php)

Responsabilidades:

- preparar una copia de trabajo,
- escribir metadata ID3,
- generar el archivo procesado,
- actualizar `archivo_mp3`,
- actualizar `processing_started_at` y `processing_finished_at`,
- emitir el evento `PodcastProcessed`.

### 5.3 Job de subida a RadioBOSS

Archivo:

- [app/Jobs/UploadRadiobossJob.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Jobs/UploadRadiobossJob.php)

Responsabilidades:

- localizar el MP3 procesado,
- resolver la carpeta remota,
- limpiar archivos antiguos del destino,
- subir vía FTP,
- verificar la subida,
- marcar `radioboss_status = radioboss_verified` o `radioboss_error`,
- emitir el evento `PodcastRadiobossUploaded`.

### 5.4 Job de subida a Archive.org

Archivo:

- [app/Jobs/UploadArchiveOrgJob.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Jobs/UploadArchiveOrgJob.php)

Responsabilidades:

- comprobar si la sincronización está habilitada,
- comprobar credenciales,
- subir el archivo a Archive.org,
- aplicar metadata,
- manejar el caso de indexación pendiente,
- marcar `archive_org_status = archive_verified` o `archive_pending_indexing`,
- emitir el evento `PodcastArchiveUploaded`.

### 5.5 Servicio de Archive.org

Archivo:

- [app/Services/ArchiveOrgPodcastService.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Services/ArchiveOrgPodcastService.php)

Responsabilidades:

- resolver identificadores,
- subir archivos,
- aplicar metadata,
- verificar el estado remoto,
- manejar indexación pendiente,
- dejar snapshot técnico de la sincronización.

### 5.6 Eventos y listeners

Archivos:

- [app/Events/PodcastProcessed.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Events/PodcastProcessed.php)
- [app/Events/PodcastRadiobossUploaded.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Events/PodcastArchiveUploaded.php)
- [app/Events/PodcastDeliveryVerified.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Events/PodcastDeliveryVerified.php)
- [app/Events/PodcastUploadFailed.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Events/PodcastUploadFailed.php)
- [app/Listeners/DispatchPodcastDistributionJobs.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Listeners/DispatchPodcastDistributionJobs.php)
- [app/Listeners/SendPodcastRadiobossNotification.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Listeners/SendPodcastRadiobossNotification.php)
- [app/Listeners/SendPodcastArchiveNotification.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Listeners/SendPodcastArchiveNotification.php)
- [app/Listeners/FinalizePodcastDelivery.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Listeners/FinalizePodcastDelivery.php)
- [app/Listeners/RecordPodcastUploadFailure.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Listeners/RecordPodcastUploadFailure.php)

## 6. Modelo de datos y estados

### 6.1 Campos relevantes en `radio_programs`

- `archivo_mp3`
- `archivo_mp3_disk`
- `processing_started_at`
- `processing_finished_at`
- `radioboss_started_at`
- `radioboss_finished_at`
- `radioboss_notification_sent_at`
- `radioboss_status`
- `radioboss_verified_at`
- `radioboss_last_error`
- `radioboss_metadata`
- `archive_started_at`
- `archive_finished_at`
- `archive_notification_sent_at`
- `sync_archive_org`
- `archive_org_status`
- `archive_org_remote_path`
- `archive_org_uploaded_at`
- `archive_org_verified_at`
- `archive_org_last_error`
- `archive_org_metadata`
- `delivery_status`
- `delivery_verified_at`
- `delivery_last_error`
- `delivery_metadata`
- `status_message`

### 6.2 Interpretación operativa

#### Procesado local

- `processing_started_at` existe y `processing_finished_at` es nulo: el MP3 sigue en proceso.
- `processing_finished_at` existe: el archivo ya quedó listo para distribuirse.

#### RadioBOSS

- `radioboss_pending`: todavía no terminó.
- `radioboss_verified`: subida correcta y verificada.
- `radioboss_error`: fallo durante la subida o la verificación.
- `radioboss_skipped`: estado heredado o de compatibilidad si aplica.

#### Archive.org

- `archive_pending`: el proceso sigue en curso.
- `archive_pending_indexing`: la subida fue correcta, pero Archive.org todavía indexa.
- `archive_verified`: subida y verificación correctas.
- `archive_error`: fallo durante la subida o la verificación.
- `archive_skipped`: no aplica por configuración o por el estado del episodio.

#### Entrega global

- `delivery_pending`: el proceso todavía no concluyó.
- `delivery_partial`: una fase terminó y la otra no.
- `delivery_verified`: ambas fases terminaron correctamente.
- `delivery_failed`: no se pudo completar ninguna fase relevante.

## 7. Flujo técnico detallado

### 7.1 Recepción del formulario

El formulario de administración envía:

- programa maestro,
- título,
- fecha de emisión,
- número de episodio,
- imagen opcional,
- MP3,
- opción de sincronización con Archive.org,
- opción de conservar copia local.

El controlador valida estos datos y crea el registro en `radio_programs`.

### 7.2 Creación del registro

Se guarda un registro operativo del episodio con:

- rutas del archivo,
- metadatos editoriales,
- estados iniciales,
- timestamps iniciales a `null`,
- mensaje inicial de estado.

### 7.3 Procesado local

`ProcessMp3Job`:

- crea una copia de trabajo,
- escribe metadata ID3,
- genera el archivo procesado,
- actualiza la ruta final,
- calcula duración cuando es posible,
- emite `PodcastProcessed`.

### 7.4 Subida a RadioBOSS

`UploadRadiobossJob`:

1. resuelve el archivo final,
2. resuelve la carpeta remota,
3. limpia archivos antiguos del directorio remoto,
4. sube el MP3,
5. verifica que el archivo exista,
6. actualiza el estado de RadioBOSS,
7. emite `PodcastRadiobossUploaded`.

### 7.5 Subida a Archive.org

`UploadArchiveOrgJob`:

1. verifica si la sincronización está activa,
2. verifica credenciales,
3. sube el archivo,
4. aplica metadata,
5. maneja el caso de indexación pendiente,
6. actualiza el estado de Archive.org,
7. emite `PodcastArchiveUploaded`.

## 8. Separación de notificaciones

### 8.1 Criterio actual

La notificación ya no depende de que el pipeline completo termine.

Ahora se notifica:

- cuando RadioBOSS queda verificado,
- cuando Archive.org queda verificado o listo con indexación pendiente.

### 8.2 Beneficio

Si el proceso se queda atascado:

- se sabe qué etapa sí terminó,
- se sabe qué etapa no terminó,
- se evita depender de un único correo final.

### 8.3 Riesgo residual

Si el correo falla pero la subida fue correcta, el estado técnico sigue siendo válido. El fallo queda en la capa de notificación, no en la subida.

## 9. Auditoría de eventos

Se agregó una tabla de eventos técnicos para tener una línea temporal por episodio.

### 9.1 Tabla

- `radio_program_events`

### 9.2 Uso

Se registran eventos como:

- `UPLOAD_RECEIVED`
- `PROCESSING_STARTED`
- `PROCESSING_COMPLETED`
- `RADIOBOSS_UPLOAD_STARTED`
- `RADIOBOSS_UPLOAD_COMPLETED`
- `RADIOBOSS_VERIFIED`
- `ARCHIVE_UPLOAD_STARTED`
- `ARCHIVE_UPLOAD_COMPLETED`
- `ARCHIVE_PENDING_INDEXING`
- `ARCHIVE_VERIFIED`
- `NOTIFICATION_SENT`
- `ERROR`
- `RECONCILE_REQUEUED`

### 9.3 Ventaja

Permite reconstruir:

- qué ocurrió,
- en qué orden,
- con qué contexto,
- cuándo se envió cada notificación.

## 10. Manejo de errores

### 10.1 Fallos en RadioBOSS

Se registran mediante:

- `radioboss_status = radioboss_error`,
- `radioboss_last_error`,
- `status_message`,
- logs,
- evento `ERROR`.

### 10.2 Fallos en Archive.org

Se registran mediante:

- `archive_org_status = archive_error`,
- `archive_org_last_error`,
- `status_message`,
- logs,
- evento `ERROR`.

### 10.3 Fallos de correo

El envío por correo tiene su propia capa de error. Si falla, no invalida la subida.

## 11. Reintentos

### 11.1 Reintento por job

Cada job conserva su propia política de reintento.

### 11.2 Reintento selectivo

El panel permite reprocesar solo la parte que falta.

### 11.3 Reintento de Archive.org

`archive_pending_indexing` no se trata como fallo inmediato. El proceso puede volver a revisarse más tarde si la indexación sigue incompleta.

## 12. Comando de reconciliación

### 12.1 Comando

Archivo:

- [app/Console/Commands/ReconcilePodcastPipelineCommand.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Console/Commands/ReconcilePodcastPipelineCommand.php)

Comando:

- `podcast:reconcile-pipeline`

### 12.2 Qué hace

El comando:

- detecta episodios con etapas pendientes o en error,
- re-dispara RadioBOSS cuando corresponde,
- re-dispara Archive.org cuando corresponde,
- reenvía notificaciones faltantes,
- completa la verificación final si todo ya quedó listo,
- deja evento de auditoría.

### 12.3 Scheduler

Se programa desde `routes/console.php` para ejecutarse periódicamente y revisar procesos atascados sin intervención manual.

## 13. Observabilidad en el panel

El dashboard administrativo muestra:

- contadores del pipeline,
- eventos recientes,
- programas recientes con su estado,
- errores por etapa.

Esto convierte el flujo en algo observable, no solo ejecutable.

## 14. Propuesta de mejora

Si se quisiera seguir endureciendo el proceso, lo siguiente sería recomendable:

1. consolidar constantes o enums para los estados,
2. añadir marcas explícitas de notificación enviada por correo,
3. guardar duración por etapa,
4. diferenciar mejor `failed` de `partial`,
5. agregar alertas cuando un episodio lleve demasiado tiempo en `processing_started_at` sin finalizar,
6. mantener un panel de línea temporal por episodio.

## 15. Validación

La suite focalizada del módulo de subida valida:

- creación del episodio,
- procesamiento de MP3,
- subida a RadioBOSS,
- subida a Archive.org,
- correos separados,
- fragmento reciente del panel.

## 16. Archivos relacionados

- [app/Http/Controllers/Admin/PodcastUploadController.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Http/Controllers/Admin/PodcastUploadController.php)
- [app/Jobs/ProcessMp3Job.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Jobs/ProcessMp3Job.php)
- [app/Jobs/UploadRadiobossJob.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Jobs/UploadRadiobossJob.php)
- [app/Jobs/UploadArchiveOrgJob.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Jobs/UploadArchiveOrgJob.php)
- [app/Console/Commands/ReconcilePodcastPipelineCommand.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Console/Commands/ReconcilePodcastPipelineCommand.php)
- [app/Services/ArchiveOrgPodcastService.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Services/ArchiveOrgPodcastService.php)
- [app/Services/PodcastPipelineAuditService.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Services/PodcastPipelineAuditService.php)
- [app/Mail/PodcastRadiobossUploadedMail.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Mail/PodcastRadiobossUploadedMail.php)
- [app/Mail/PodcastArchiveUploadedMail.php](/c:/laragon/www/Plantilla/SevenRockRadio/app/Mail/PodcastArchiveUploadedMail.php)
- [tests/Feature/AdminPodcastUploadTest.php](/c:/laragon/www/Plantilla/SevenRockRadio/tests/Feature/AdminPodcastUploadTest.php)
