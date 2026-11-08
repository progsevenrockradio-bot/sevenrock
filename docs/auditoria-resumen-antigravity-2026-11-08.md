# Comprobación del resumen de Antigravity

Fecha: 8 de noviembre de 2026. Proyecto: SevenRockRadio.

> Actualización posterior al comparar todas las ramas con GitHub: esta auditoría describe la rama activa main y sus cambios sin commit. Se ha encontrado la rama exclusivamente local savepoint-all-work-pre-release (696e9b1), que SÍ contiene TalentReferralPilotService, protección de Pro, integración de pagos/reembolsos y cuatro migraciones adicionales. Por tanto, el piloto no está ausente de todo el proyecto: está guardado en otra rama y pendiente de integración. Véase estado-github-local-2026-11-08.md. Las pruebas indicadas aquí siguen correspondiendo únicamente al checkout activo, no a esa rama de respaldo.

## Dictamen

El resumen describe numerosas funcionalidades existentes, y reproduce correctamente el commit local y las pruebas específicas de RadioBOSS. Sin embargo, mezcla implementación local con despliegue, presenta reglas de referidos que NO corresponden al código actual y exagera el grado de verificación global. No debe usarse todavía como certificado de estabilidad o lista definitiva de tareas terminadas.

Alcance: lectura del resumen aportado, historial y diferencias Git, revisión de código y ejecución de 100 pruebas seleccionadas. No se ha accedido a Hostinger, al FTP real ni a la interfaz de RadioBOSS. No se han ejecutado migraciones, publicado cambios ni modificado código funcional. La base de datos de las pruebas se configura como SQLite en memoria en phpunit.xml. No se ha auditado visualmente la web ni repetido la compilación Vite.

## Estado contrastado por bloque

| Bloque | Evidencia local | Resultado |
|---|---|---|
| Versión local | HEAD 6de294d | Coincide con el resumen. No acredita la versión de producción. |
| Cambios recientes RadioBOSS | Controlador editorial, modelo, jobs, validador nuevo, vista, configuración y tests sin commit | Existen; están pendientes de consolidación y despliegue verificado. |
| Entrega por staging, respaldo y reconciliación | UploadRadiobossJob y ReconcilePodcastPipelineCommand; tarea cada diez minutos | Implementado. Las pruebas de entrega y reconciliación seleccionadas pasan. |
| Edición editorial posterior a entrega | Rama específica en AdminPodcastEditorialController::update | Implementada para título, resumen, reseña, biografía y estado de inclusión. No reprocesa audio en esa rama. |
| Validación ASCII y nombre técnico | RadioBossPathValidator y metadatos de RadioProgram | Existe, pero la confirmación no es un requisito estricto en todos los caminos. Véase abajo. |
| Subidas R2/Uppy | resources/js/app.js y ConductorEpisodeController | Implementadas. El límite del controlador de conductores es 524288000 bytes (500 MiB), no 300 MB. No se realizó una subida real de ese tamaño. |
| Reproductor | RadioPlayerService, getters tituloVisible/subtituloVisible y BroadcastChannel | Implementado; las pruebas seleccionadas del servicio y reconocimiento de programas pasan. La sincronización observada es entre ventanas del navegador; no acredita sincronización entre dispositivos distintos. |
| Parrilla multidía y catálogo | Código, historial y pruebas de persistencia/optimización | Las pruebas seleccionadas pasan; existen tres fallos adicionales en pruebas de ventanas de emisión. |
| Talentos/promotores | Controladores, migraciones, servicio y tests | Gestión de códigos y parte del ciclo implementados. Recompensas y garantías comerciales del resumen no coinciden con este checkout. |
| Dark Vader | Verificación del remitente, resolución de otras fuentes y vía editorial | Implementado, pero no restringe todo el procesamiento IMAP exclusivamente a Dark Vader. También procesa otras fuentes. Cuatro pruebas antiguas seleccionadas fallan. |
| Colisiones processed_emails | updateOrInsert en ProcessExternalEmailWithAiJob | Existe esa protección en el job; no debe generalizarse como una comprobación exhaustiva de todos los caminos de correo. |
| En memoria / Hoy en el Rock | Implementación y pruebas PostEnMemoriaTest/EfemeridesExclusionTest | Pruebas seleccionadas satisfactorias. |
| Lucille y encabezados | Historial 922264b/6de294d, vistas y assets | Cambios presentes. Capturas, igualdad visual desktop/móvil y publicación remota no verificadas aquí. |
| Assets y respaldo | Manifest modificado, CSS nuevo y ZIP local | Existen. No acredita compilación reproducible, integridad del ZIP ni sincronización con Hostinger. |

## Diferencias y riesgos concretos

### 1. Referidos: no está listo el piloto descrito

En app/Services/TalentReferralService.php, el código ejecutable aplica:

- 12 referidos activos: 30 días.
- 18 referidos activos: 60 días.
- Cada 3 referidos pagados: 90 días, sin el límite de dos recompensas descrito.

No corresponde a «un mes Básico por cada tres primeros pagos, máximo dos recompensas». Las constantes de app/Models/TalentReferral.php confirman los hitos antiguos.

Además, applyBonusDays cambia el plan a basic cuando rewardKey es tier1, incluso si la suscripción era Pro. Esto contradice la garantía de no degradación. No se aprecia un estado de resolución manual de Pro en este servicio.

La búsqueda en app encuentra la definición de markReferralPaid, pero ninguna llamada a ese método. Por tanto, la integración del primer pago con la recompensa no queda implementada por el mero hecho de existir el servicio. Tampoco quedan acreditadas las garantías de exclusión de cortesías y anulación de pagos descritas en el resumen.

Acción: definir la regla comercial vigente, conectar confirmación de pago y reversos, proteger Pro, implementar límite e idempotencia y añadir pruebas de esas reglas antes de activar la campaña. Ejecutar una migración por sí solo no resuelve estas diferencias.

### 2. Confirmación de nombre RadioBOSS: protección parcial

UploadRadiobossJob usa el nombre confirmado cuando existe; si no, normaliza el nombre automáticamente. No bloquea simplemente por faltar confirmación humana de un original acentuado. La vista de aprobación introduce la propuesta segura en un campo oculto y approve la guarda como confirmada. El campo confirm_radioboss_filename se valida en update, pero no gobierna esa operación.

Acción: decidir si aprobar el episodio equivale a confirmar el nombre técnico. Si se exige una confirmación independiente, aplicarla en el servidor y en todos los puntos de entrega, con una prueba específica. El resumen no debería afirmar actualmente una confirmación obligatoria independiente.

### 3. Validación ASCII no significa compatibilidad Windows completa

isSafeRemoteName rechaza tildes, separadores, caracteres prohibidos y referencias relativas. No contempla todos los nombres reservados de Windows: por ejemplo CON.mp3 satisface sus condiciones. generateSafeProposal también puede devolver un nombre con '..' que la validación rechaza.

Acción: cubrir nombres reservados y exigir que toda propuesta generada pase la misma validación efectiva. Esto no invalida la solución de tildes, pero sí la afirmación de blindaje total.

### 4. Episodios legados y selección al aire

La configuración nueva preserve_unrotatable_legacy_files es true por defecto. El job permite continuar con el nuevo episodio cuando falla la rotación de ciertos nombres legados. Por tanto, pueden coexistir episodios anteriores y nuevos en la carpeta de emisión.

Acción prioritaria: comprobar el criterio real de selección del evento de RadioBOSS. Una entrega FTP correcta no garantiza qué episodio se emitirá. isDeliveredToRadioboss usa el indicador enviado o radioboss_verified; tampoco demuestra que se haya escuchado el episodio al aire.

### 5. Colas: el resumen omite tiempos existentes

routes/console.php también inicia queue:work cada minuto con timeout=3600 y withoutOverlapping. SendMarketingCampaignJob declara timeout=3600. config/queue.php tiene retry_after=90 por defecto, sustituible mediante DB_QUEUE_RETRY_AFTER.

Por tanto, la recomendación aislada DB_QUEUE_RETRY_AFTER=2000 no cubre todos los trabajos de 3600 segundos. Hay que revisar conjuntamente conexiones, colas, límites por job y workers reales de Hostinger. No se ha leído ni confirmado la configuración efectiva del servidor.

### 6. Portada después de entregar

La rama editorial de episodios entregados devuelve éxito después de guardar textos, pero no procesa los campos de portada cuando se elige sheet_only. La vista ofrece un archivo de portada y ese alcance.

Acción: comprobar y corregir el caso de cambiar solo la portada de la ficha después de entregar, o retirar esa opción para ese estado. Las 41 pruebas satisfactorias no certifican esta combinación.

### 7. Diseño y reglas actuales del proyecto

Las vistas inspeccionadas, incluida la revisión editorial modificada, contienen colores hexadecimales y clases de paleta fija. Esto no coincide con la regla actual de AGENTS.md de colores semánticos y componentes pinion-ui. La existencia del diseño Lucille no acredita cumplimiento de esa regla. No se realizó una auditoría visual ni ui:lint en esta revisión.

## Verificación automatizada realizada

PHP utilizado: ejecutable local de Laragon 8.3.36; php no estaba disponible directamente en PATH.

1. RadioBossFilenameAndPostDeliveryEditorialTest + RadioBossDeliveryPipelineTest: **41 aprobadas, 291 aserciones**, aproximadamente 5,5 segundos. Coincide exactamente con el resumen.
2. Selección ampliada: **59 pruebas, 50 aprobadas, 9 fallidas, 289 aserciones y 2 marcadas como risky**.

Selección ampliada: PromoterCodeTest, TalentSystemFeatureTest, CatalogAndScheduleOptimizationTest, MasterProgramEmisionesPersistenceTest, MasterProgramEmisionDurationTest, RadioPlayerServiceTest, RadioPlayerProgramMatchTest, ReconcilePodcastPipelineCommandTest, Prompt31DirectNewsAndAiLimitTest, PostEnMemoriaTest, EfemeridesExclusionTest y PodcastChunkUploadTest.

Desglose de las nueve fallidas:

| Grupo | Cantidad | Observación y siguiente comprobación |
|---|---:|---|
| MasterProgramEmisionDurationTest | 3 | Resultados true donde se espera false. Revisar márgenes y coherencia entre servicios de horario y fixtures. |
| PodcastChunkUploadTest | 1 | HTTP 500: no se puede escribir en storage/app/podcast-chunks durante la prueba local. Separar permisos/compatibilidad del entorno de un fallo del flujo. |
| Prompt31DirectNewsAndAiLimitTest | 4 | El remitente simulado no supera la verificación nueva; mocks de IA y textos esperados no coinciden con el pipeline actual. Actualizar y contrastar pruebas antes de atribuirlo a producción. |
| TalentSystemFeatureTest | 1 | La prueba espera purgar talent_media tras la gracia, pero el registro permanece. Revisar política, fechas y tarea de expiración. |

Total: **100 pruebas seleccionadas, 91 aprobadas y 9 fallidas**. No es la suite completa. No se han corregido fallos como parte de esta comprobación.

## Pendientes ordenados

1. Resolver la diferencia de reglas de referidos y proteger cuentas Pro antes de lanzar la campaña.
2. Investigar los nueve fallos y las dos pruebas risky; distinguir regresiones de pruebas antiguas y limitaciones locales.
3. Cerrar la semántica de confirmación técnica y el cambio de portada posterior a entrega.
4. Verificar selección de episodios en RadioBOSS y estado real de archivos legados/temporales; no borrar basándose solo en el listado histórico del resumen.
5. Revisar conjuntamente cron, workers y tiempos de reserva/reintento en producción.
6. Preparar commit revisado con todos los cambios locales relacionados, incluidos configuraciones, listener, FileUploadService, modelo, tests, validador y assets. El paquete es más amplio que tres archivos.
7. Compilar y comprobar assets, desplegar con un procedimiento controlado y verificar versión y funcionamiento remotos.
8. Consultar estado real de migraciones antes de decidir cuáles ejecutar; no se ha confirmado que la migración de promotores esté pendiente en Hostinger.
9. Revisar compatibilidad con las reglas actuales de UI y repetir comprobación visual responsive.
10. Mantener comprobaciones operativas de R2, FTP, Archive.org y APIs externas; vigencia de tokens y cuotas no verificadas aquí.

## Datos de producción que siguen sin acreditar

Commit desplegado; migraciones ejecutadas; configuración y ejecución de los tres cron; valor efectivo de DB_QUEUE_RETRY_AFTER; cuatro temporales concretos; regla de selección de pistas; publicación de los assets; integridad del respaldo; ausencia de incidencias reales y estado de cuotas externas.

El estado prudente es: funcionalidades principales presentes y mejoras locales de RadioBOSS con pruebas específicas satisfactorias, pero piloto de referidos diferente al descrito y validación global todavía incompleta.
