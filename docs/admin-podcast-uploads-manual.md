# Seven Rock Radio - Manual de `admin/podcast-uploads`

Este documento describe cada opcion visible en `http://127.0.0.1:8000/admin/podcast-uploads`, el efecto de cada campo y el impacto real en el pipeline de episodios.

## Proposito

La pantalla centraliza la subida y el procesamiento de episodios:

- Subida del MP3 principal
- Imagen o carátula del episodio
- Metadatos editoriales
- Opciones de distribución
- Lista de últimos episodios procesados

Los datos se guardan en `radio_programs` y se distribuyen mediante jobs de `ProcessMp3Job`, `UploadRadiobossJob`, `UploadArchiveOrgJob` y `NotifyPodcastReadyJob`.

## Reglas generales

- El formulario hace `POST` a `admin.podcast-uploads.store`.
- El progreso de subida se maneja en el frontend con Alpine.js.
- Si falta un campo obligatorio, Laravel devuelve errores y la pestaña afectada se abre automáticamente.
- El estado final del episodio no se edita a mano: lo calcula el pipeline.

## Vista interna

La página interna de `/admin/podcast-uploads/manual` incluye:

- índice lateral fijo
- scrollspy visual
- numeración visible por bloque
- botón para exportar un PDF real desde servidor

## Mapa por bloques

La vista está organizada en 3 pestañas:

1. `Datos editoriales`
2. `Multimedia pesada`
3. `Distribución técnica`

## Bloque 1: Datos editoriales

### `master_program_id`

- Selecciona el programa maestro al que pertenece el episodio.
- Agrupa el episodio dentro de la grilla editorial.

### `numero_episodio`

- Número manual opcional.
- Si se deja vacío, el sistema calcula el siguiente correlativo.

### `live_title`

- Título visible del episodio.
- Se reutiliza en ficha, archivo y notificación.

### `fecha_emision`

- Fecha de publicación o emisión del episodio.

### `biografia_invitado`

- Texto corto para identificar al invitado.
- Es opcional.

### `resena`

- Resumen o descripción amplia del episodio.
- Puede actuar como texto editorial base para avisos y fichas.

## Bloque 2: Multimedia pesada

### `archivo_mp3`

- Archivo de audio principal del episodio.
- Debe ser un MP3 válido.
- Es el insumo más importante de la pantalla.

### `imagen_episodio_url`

- URL pública de la imagen del episodio.
- Se usa como carátula alternativa o complemento.

### `imagen_episodio_file`

- Archivo local de la carátula.
- Tiene prioridad sobre la URL cuando ambos están presentes.

## Bloque 3: Distribución técnica

### `download_processed_mp3`

- Conserva una copia local del MP3 procesado.
- Permite descarga posterior desde el panel.

### `sync_archive_org`

- Activa la sincronización con Archive.org.
- Si está desactivado, el pipeline omite esa entrega.

### Estado operativo

El panel muestra el estado implícito del episodio:

- `Borrador`: antes de subir o procesar
- `Procesando`: durante la carga o los jobs
- `Publicado`: cuando el pipeline terminó bien

Ese estado no se edita manualmente desde esta pantalla.

## Últimos episodios

La sección inferior:

- refresca el listado automáticamente
- permite reprocesar episodios con problemas
- permite descargar el MP3 local
- permite eliminar un episodio

## Riesgos habituales

- Un MP3 inválido bloquea la subida.
- Una URL de imagen inválida genera error de validación.
- Un programa maestro mal seleccionado rompe la relación del episodio.
- Un fallo en RadioBOSS o Archive.org deja el episodio en estado parcial.

## Resumen corto

- Datos editoriales: programa, fecha, número, título, invitado y resumen.
- Multimedia pesada: archivo de audio e imagen.
- Distribución técnica: copia local y sincronización externa.
- Resultados: la lista de últimos episodios muestra el estado del pipeline.

## Capturas sugeridas

1. Pantalla completa de `admin/podcast-uploads`.
2. Bloque de datos editoriales abierto con programa maestro seleccionado.
3. Bloque multimedia pesada con progreso activo.
4. Bloque de distribución técnica con los toggles visibles.
5. Lista de últimos episodios con estado de pipeline.

## PDF compacto

La ruta `/admin/podcast-uploads/manual/pdf` genera una versión resumida para impresión rápida.
Ese PDF prioriza índice, resumen ejecutivo y campos clave por bloque para que quede en dos páginas y sea fácil de imprimir o compartir.
