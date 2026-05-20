# SevenRockRadio - Implementation Report

Fecha de corte: 2026-05-18

Este documento resume, en orden de ejecución, qué se agregó al proyecto nuevo `SevenRockRadio`, qué archivos se crearon o modificaron, qué problema resolvía cada bloque y qué partes se omitieron de forma intencional.

---

## 1. Objetivo general

La migración no se planteó como una copia literal del sitio viejo, sino como una reconstrucción funcional sobre Laravel con:

- Blade como capa de vistas
- un layout público reutilizable
- un reproductor de radio operativo
- un flujo de metadata más limpio
- administración centralizada del branding
- compatibilidad con podcasts / Archive.org
- una base de datos limpia y separada del ruido de WordPress y plugins viejos

La prioridad fue preservar lo que realmente aporta valor al sitio musical:

1. identidad visual
2. navegación pública
3. reproductor de radio
4. programación editorial
5. podcasts
6. info de banda / letra
7. panel administrativo para mantenerlo todo editable

---

## 2. Orden real de trabajo

### Fase 1 - Base del proyecto y migración estructural

Se creó la nueva base funcional de Laravel y se dejó lista para sustituir el flujo legado de WordPress.

Se trabajó sobre:

- estructura general de `SevenRockRadio`
- layout Blade público
- configuración de marca / tema
- CRUD y modelos base
- integración con datos reales del proyecto viejo

### Fase 2 - Limpieza y auditoría de base de datos

Se revisó el dump SQL del entorno antiguo para separar:

- tablas que sí tienen valor
- tablas legacy
- tablas de plugins
- ruido de staging / clones

Resultado:

- se creó un documento de auditoría
- se creó un SQL por fases para limpieza
- se exportó una whitelist de tablas útiles
- se importó una base limpia al proyecto nuevo

### Fase 3 - Reproductor de radio

Se reconstruyó el player en Blade + JS para cubrir:

- play / pause
- volumen
- mute
- minimizado
- detalles
- metadata de canción
- metadata de banda
- letra
- warmup y sincronización

### Fase 4 - Programación, podcasts y banda

Se portó y/o reescribió la lógica para:

- `ProgramScheduleService`
- bloques de home como “Próximo programa”
- podcasts recientes
- Archive.org
- resolución de bandas y letras

### Fase 5 - Ajuste fino visual y de UX

Se corrigieron:

- ticker editorial más delgado
- distribución del player
- peso visual del menú
- comportamiento del modal
- jerarquía visual de la home

---

## 3. Cambios principales por bloque funcional

## 3.1. Branding y layout público

### Qué se hizo

Se creó o ajustó el layout público para que el branding no dependiera de textos duros como “Lucille”.

### Qué resolvió

- nombre del sitio editable desde admin
- brand mark editable
- fuente editable
- color editable
- menú público coherente con la marca

### Archivos involucrados

- `resources/views/components/navigation/rocks-menu.blade.php`
- `resources/views/components/layouts/site.blade.php` o equivalente del layout base
- `app/Models/ThemeSetting.php`
- `app/Http/Controllers/Admin/...` relacionado con settings

### Resultado

El frontend dejó de depender de branding fijo y pasó a leer la configuración del panel.

---

## 3.2. Reproductor de radio

### Qué se hizo

Se reescribió el reproductor para que funcionara como una unidad propia, sin depender de una mezcla frágil de scripts viejos.

Incluye:

- play / pause
- volumen
- mute
- minimización
- panel de detalles
- modal de banda
- sincronización con metadata
- toasts de estado

### Qué resolvió

- el player dejó de estar amarrado al frontend viejo de WordPress
- se separó el estado visual del audio real
- se agregó soporte para `now playing`, historial y metadata ampliada

### Archivos involucrados

- `resources/js/player.js`
- `resources/views/components/radio/player.blade.php`
- `app/Services/RadioPlayerService.php`
- `app/Http/Controllers/Api/RadioWebhookController.php`

### Notas técnicas

- se eliminaron dobles inicializaciones del audio
- se ajustó la UI para que el bloque de acciones no quedara pegado al borde
- se mejoró la relación visual entre carátula, canción, banda y volumen
- se corrigieron varios estados que hacían que el botón pareciera activo pero el audio no saliera

---

## 3.3. Info de banda y letra

### Qué se hizo

Se construyó una ruta de resolución de banda y letra que prioriza:

1. datos locales
2. enriquecimiento desde servicios externos
3. persistencia en base de datos
4. lectura local posterior

### Qué resolvió

- el modal de detalles ya no depende solo del HTML estático
- se evita bloquear la respuesta mientras se resuelven fuentes externas
- se permite guardar lo obtenido para no repetir llamadas

### Archivos involucrados

- `app/Http/Controllers/Api/BandInfoController.php`
- `app/Support/BandInfoResolver.php`
- `app/Support/BandInfoAggregator.php`
- `app/Support/LyricsResolver.php`
- `app/Services/RadioPlayerService.php`
- `app/Http/Controllers/Api/RadioWebhookController.php`

### Resultado

El sistema quedó preparado para el flujo:

`internet -> persistencia -> lectura local`

Eso significa:

- si existe contenido guardado, se usa ese
- si no existe, se intenta resolver
- si no responde una fuente externa, no se rompe la vista

---

## 3.4. Programación y “Próximo programa”

### Qué se hizo

Se reescribió la lógica de programación para que no dependa de una lista vieja o de ordenamientos engañosos.

### Qué resolvió

- el bloque “Próximo programa” dejó de mostrar episodios en orden incorrecto
- la lógica pasó a priorizar hora real de inicio y agenda efectiva
- se preparó la integración con la tabla usada por programación viva

### Archivos involucrados

- `app/Support/ProgramScheduleService.php`
- `app/Models/MasterProgram.php`
- `database/migrations/2026_05_17_000001_create_master_programs_table.php`
- `database/seeders/MasterProgramsSeeder.php`
- `database/seeders/DatabaseSeeder.php`

### Notas

El problema detectado no era solamente visual. La base de datos y el origen de los registros estaban mezclando:

- programas en vivo
- podcasts
- entradas legacy

Se separó el criterio para que el home muestre la programación viva correcta.

---

## 3.5. Podcasts y Archive.org

### Qué se hizo

Se portó y reforzó la capa de podcasts para que:

- lea episodios reales
- respete fechas
- use archive metadata cuando exista
- haga fallback sin romper la lista

### Qué resolvió

- el bloque de podcasts dejó de depender solamente de una tabla vacía o incompleta
- se corrigió el criterio de selección
- se añadió compatibilidad con contenido real importado y sincronizado

### Archivos involucrados

- `app/Services/ArchiveOrgService.php`
- `resources/views/components/home/latest-podcasts.blade.php`
- `resources/views/pages/home.blade.php`
- `database/seeders/data/`

### Notas técnicas

Se detectó que en el proyecto viejo había una falla en el uploader de Archive.org relacionada con `fclose()` de un stream no válido. Ese bug fue corregido en el proyecto viejo para evitar la ruptura de sincronización.

---

## 3.6. Base de datos y modelo de datos

### Qué se hizo

Se analizó el dump antiguo y se separó:

- lo útil
- lo legacy
- lo de plugins
- lo de staging / clones

Después se importó una base limpia al proyecto nuevo.

### Qué resolvió

- se evitó arrastrar ruido de WordPress
- se redujo la base a las tablas que realmente sirven a la app nueva
- se preparó una estructura más estable para Laravel

### Archivos creados

- `docs/database-audit.md`
- `docs/database-whitelist.md`
- `docs/database-cleanup-phases.sql`
- `docs/sevenrock_whitelist_extracted.sql`
- `docs/extract-whitelist-from-dump.ps1`
- `docs/export-whitelist.ps1`

### Migraciones relevantes

- `database/migrations/2026_05_17_000001_fix_band_profiles_primary_key.php`
- `database/migrations/2026_05_17_000002_add_band_profile_fk_to_songs_table.php`

### Resultado

- `band_profiles` quedó con clave primaria válida
- `songs.band_profile_id` quedó como FK
- el modelo de contenido quedó mejor ligado

---

## 3.7. Panel de administración

### Qué se hizo

Se extendieron formularios y controladores del admin para permitir:

- branding
- fuentes
- colores
- asignación de banda a canciones
- gestión de contenido del player
- edición de programación

### Archivos involucrados

- `app/Http/Controllers/Admin/SongController.php`
- formularios blade del admin
- controladores de settings y contenido relacionado

### Resultado

El contenido del sitio dejó de estar amarrado a valores fijos en la vista.

---

## 3.8. Ticker / cintillo editorial

### Qué se hizo

Se rediseñó para que sea más delgado y menos agresivo visualmente.

### Qué resolvió

- redujo peso visual
- mejoró la jerarquía con respecto al resto del home
- evitó que el cintillo compitiera con los bloques principales

### Archivo involucrado

- `resources/views/components/home/headline-ticker.blade.php`
- `app/Support/HeadlineTickerService.php`

---

## 4. Archivos nuevos creados

### Código / modelo / servicios

- `app/Models/MasterProgram.php`
- `app/Services/ArchiveOrgService.php`
- `app/Support/HeadlineTickerService.php`
- `database/migrations/2026_05_17_000001_create_master_programs_table.php`
- `database/migrations/2026_05_17_000001_fix_band_profiles_primary_key.php`
- `database/migrations/2026_05_17_000002_add_band_profile_fk_to_songs_table.php`
- `database/seeders/MasterProgramsSeeder.php`

### Documentación / auditoría

- `docs/implementation-report.md`  *(este informe)*
- `docs/database-audit.md`
- `docs/migration-gap.md`
- `docs/database-whitelist.md`
- `docs/database-cleanup-phases.sql`
- `docs/sevenrock_whitelist_extracted.sql`
- `docs/extract-whitelist-from-dump.ps1`
- `docs/export-whitelist.ps1`

### Recursos de vista

- `resources/views/components/home/headline-ticker.blade.php`

### Directorio de soporte de datos

- `database/seeders/data/`

---

## 5. Archivos modificados

### Controladores

- `app/Http/Controllers/Admin/SongController.php`
- `app/Http/Controllers/Api/BandInfoController.php`
- `app/Http/Controllers/Api/RadioWebhookController.php`
- `app/Http/Controllers/SiteController.php`

### Servicios / soporte

- `app/Services/RadioPlayerService.php`
- `app/Support/BandInfoAggregator.php`
- `app/Support/BandInfoResolver.php`
- `app/Support/LyricsResolver.php`
- `app/Support/ProgramScheduleService.php`

### Migraciones / seeders

- `database/migrations/2026_05_13_000002_create_programs_table.php`
- `database/migrations/2026_05_13_000004_create_play_history_table.php`
- `database/seeders/DatabaseSeeder.php`

### Frontend

- `resources/js/player.js`
- `resources/views/components/home/latest-podcasts.blade.php`
- `resources/views/pages/home.blade.php`

---

## 6. Qué se omitió de forma intencional

No se portó todo el proyecto viejo. Eso fue deliberado.

### Omitido por alcance funcional

- social publishing completo
- marketplace / creator stack
- bandejas de moderación complejas
- flujos de claims de bandas
- automatizaciones no críticas

### Omitido por costo / ruido

- tablas de plugins de WordPress que no aportan valor al nuevo sitio
- staging / clones de la base
- SEO plugins viejos
- sliders y utilidades antiguas que no eran parte del producto final

### Omitido por seguridad / mantenimiento

- llamadas pesadas que bloqueen requests de usuario
- dependencia ciega de APIs externas sin fallback
- lógica duplicada entre frontend y backend

---

## 7. Qué se cambió respecto al proyecto viejo

### Antes

- WordPress + plugins + tablas mezcladas
- programaciones poco claras
- podcasts dependientes de rutas y formatos heredados
- metadata de banda distribuida entre varios puntos
- UI pesada y muy amarrada al tema original

### Ahora

- Laravel limpio
- Blade modular
- datos importados y clasificados
- player desacoplado
- schedule y metadata con fallback
- admin para mantener contenido sin tocar código

---

## 8. Riesgos y limitaciones actuales

### Datos

- si la base no tiene un registro bien mapeado, el bloque correspondiente puede caer a fallback
- algunos `archive_identifier` importados necesitan revisión manual

### Servicios externos

- `band-info` y letras pueden depender de la calidad de respuesta de proveedores externos
- si no hay suficiente metadata, el sistema responde con fallback, no con error

### Entorno local

- el servidor local debe tener la base activa y MySQL levantado
- si MySQL no responde, la aplicación puede entrar en error en páginas que leen contenido desde BD

---

## 9. Conclusión

La migración ya no está en fase de maqueta. Hay una base funcional real con:

- programación viva
- player
- banda / letra
- podcasts
- branding editable
- limpieza estructural de la base

Lo que queda ahora es más de ajuste fino y cierre de cobertura de datos que de arquitectura base.

