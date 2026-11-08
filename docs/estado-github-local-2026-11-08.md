# Estado GitHub y computador — SevenRockRadio

Fecha de comprobación: 8 de noviembre de 2026.
Repositorio consultado directamente mediante Git: https://github.com/progsevenrockradio-bot/sevenrock

## Conclusión

GitHub main y main local apuntan al mismo commit, pero el computador contiene dos conjuntos de trabajo que no están en main remoto: cambios sin commit de RadioBOSS y una rama de respaldo amplia. El piloto nuevo de referidos existe en esa rama de respaldo. Para unificar no basta con subir los archivos actualmente modificados.

## Inventario verificado

| Ubicación | Rama/estado | Commit | Situación |
|---|---|---|---|
| GitHub | main | 6de294daecd2e324e73c6faeaa43628bd0787440 | Coincide con el commit de main local. |
| Computador | main activa | 6de294d | Tiene además 13 archivos versionados modificados, 3 eliminados y 7 archivos nuevos sin seguimiento al tomar el inventario. |
| Computador | savepoint-all-work-pre-release | 696e9b1 | Rama local no publicada como rama en GitHub; contiene trabajo adicional importante. |
| GitHub y computador | feature/moderation | b71cb61b5af94bb7bbe4f4f6756854926a6dca85 | Ambas referencias coinciden; rama divergente y antigua respecto a main. |
| GitHub | backup-produccion-20260530 | 50c4c15c7338c88376611090c8b658599ab7f51e | Existe también la referencia remota local con el mismo hash. Es un respaldo histórico, no una medición del servidor actual. |
| Hostinger | Producción | No comprobado | No se ha accedido al servidor en esta revisión. |

Los siete archivos nuevos incluían el informe de auditoría anterior, el validador, el nuevo test, tres assets CSS y el ZIP de respaldo. Este informe añade después un octavo archivo nuevo. Los tres archivos eliminados son assets CSS antiguos sustituidos por los nuevos.

La consulta remota devolvió tres ramas y ninguna etiqueta. Las referencias remotas locales coinciden con los hashes consultados directamente. No fue necesario hacer pull, checkout ni sobrescribir el trabajo activo. No se revisaron PR ni ejecuciones de GitHub Actions mediante la API; no se encontró directorio .github en main ni en el respaldo local inspeccionado.

## Cómo se separó el trabajo

Las ramas main y savepoint-all-work-pre-release comparten como base 5fa25a1. Desde ahí:

- main añadió 6de294d, con normalización ASCII para RadioBOSS y encabezados.
- El respaldo añadió 696e9b1, un único commit grande de trabajo acumulado.
- Sobre main existen ahora mejoras posteriores sin commit.

El respaldo fue creado el 8 de noviembre a las 12:54:53 +0100, y el commit de main a las 12:59:52 +0100. Son ramas hermanas: ninguna sustituye por completo a la otra.

## Qué hay solamente en la rama de respaldo respecto a la base común

185 archivos afectados, 77031 líneas añadidas y 432 eliminadas. Estas cifras incluyen grandes documentos, parches y archivos de respaldo; no son 77031 líneas nuevas de aplicación.

Distribución de archivos de aplicación y pruebas: 51 en app, 4 en config, 4 migraciones, 33 en resources, 2 en routes y 28 en tests. Además contiene documentación, ZIP, inventarios, assets y material de desarrollo.

Bloques destacados:

1. **Piloto de promotores y referidos:** TalentReferralPilotService, TalentReferralReward, cambios de panel y TalentReferralPilotTest. El servicio contempla una recompensa de un mes por tres bandas, un segundo hito a las seis, y resolución manual para Pro.
2. **Pagos y suscripciones:** TalentSubscriptionService, identificación de pagos manuales, cortesías, reversos y prevención de operaciones duplicadas. Este servicio llama realmente a evaluatePromoterRewards y handleRefundedReferral.
3. **Separación del piloto y los premios antiguos:** TalentReferralService evita aplicar hitos antiguos a participantes con código de promotor.
4. **Talentos y planes:** cambios de ciclo de suscripción, recuperación, avisos, páginas de planes y sus pruebas.
5. **Muro y envíos:** SubmissionFollowupService, SubmissionPublisher, seguimiento, consentimientos y correos.
6. **Audiencia:** informes diarios/semanales, servicios, vistas y pruebas.
7. **Inventario de almacenamiento:** herramientas de inventario de Hostinger/R2 y manifiestos de trabajo.
8. **Pruebas y revisión editorial:** pruebas adicionales de noticias, SEO, accesibilidad, navegación, privacidad y reproductor.

Esto confirma presencia de implementación, no ejecución satisfactoria de los tests ni despliegue de estos bloques. No se cambiaron ramas para ejecutarlos en esta revisión.

### Migraciones adicionales guardadas

- 2026_11_04_212028_add_release_tracking_to_track_submissions_table.php
- 2026_11_04_221500_add_consent_and_muro_followups_tables.php
- 2026_11_05_223000_add_manual_payment_fields_to_talent_subscriptions_table.php
- 2026_11_06_140000_add_pilot_referral_and_courtesy_support.php

No están en el main activo. Su presencia en el respaldo no demuestra si alguien las ejecutó manualmente en una base de datos.

## Qué hay sin commit en main local

- AdminPodcastEditorialController: nombre técnico y edición posterior a entrega.
- RadioBossPathValidator nuevo.
- UploadRadiobossJob: validación y manejo de legados/respaldos.
- ProcessMp3Job y FileUploadService: lectura y rutas temporales.
- RadioProgram: estado entregado y metadatos del nombre técnico.
- RecordPodcastUploadFailure: registro del error.
- config/filesystems.php y config/services.php: ajustes FTP/legados.
- Vista editorial y pruebas de entrega/nombres.
- Manifest y CSS compilado nuevo, con retirada de tres assets antiguos.
- ZIP de respaldo y documentación de auditoría.

Las 41 pruebas específicas de este checkout pasaron en la revisión anterior. La selección ampliada tuvo nueve fallos. Nada de ello valida automáticamente la futura combinación con el respaldo.

## Posibles conflictos al unificar

Se ejecutó una previsualización de fusión sin modificar archivos entre los commits de main y respaldo. Detectó conflicto textual en public/build/manifest.json e incompatibilidad de fusión de sus variantes comprimidas .br y .gz. No aparecieron otros marcadores textuales en esa simulación histórica. Se deben regenerar assets desde el código integrado, no elegir un manifest antiguo arbitrariamente.

Esa simulación NO incluye los cambios sin commit actuales. Hay cuatro archivos fuente/pruebas modificados tanto en esos cambios como en el respaldo:

- app/Jobs/UploadRadiobossJob.php
- app/Models/RadioProgram.php
- config/services.php
- tests/Feature/Radio/RadioBossDeliveryPipelineTest.php

Son puntos de revisión semántica obligada; coincidencia de archivo no implica necesariamente conflicto de líneas. Los assets también se solapan. git diff --check del trabajo actual no reportó errores de espacios.

## Rama feature/moderation

La comparación arroja 66 commits exclusivos de main y uno exclusivo de feature/moderation. El commit b71cb61 incluye emisiones, duración real, auditoría horaria, reproductor y pruebas. No es ancestro de main y git cherry no lo reconoce como parche idéntico integrado.

Main ya contiene funcionalidades relacionadas y cambios posteriores. Por ello, la existencia de ese commit exclusivo no demuestra que todas sus funciones falten. Antes de fusionarlo, comparar sus cambios útiles con la implementación actual y recuperar únicamente lo necesario; una fusión completa puede reintroducir código o assets antiguos.

## Ruta propuesta para una versión unificada

1. Preservar explícitamente los cambios actuales de RadioBOSS en un commit de trabajo y conservar intacta la rama de respaldo.
2. Preparar una rama de integración desde main, recuperando el código y las pruebas del respaldo por bloques coherentes. Revisar qué documentación, ZIP, inventarios y material de desarrollo debe quedar fuera del despliegue público.
3. Integrar las mejoras actuales de RadioBOSS revisando los cuatro archivos solapados.
4. Evaluar b71cb61 frente al resultado para no perder correcciones horarias ni duplicar implementaciones.
5. Ejecutar pruebas de la versión combinada: RadioBOSS, pagos/reversos, referidos/Pro, talentos, muro, noticias, horarios y reproductor; investigar los nueve fallos detectados previamente.
6. Compilar assets del resultado, comprobar manifest y presentación; revisar las reglas UI del proyecto.
7. Publicar una rama revisable en GitHub y dejar identificada la versión candidata antes de actualizar main.
8. Inspeccionar Hostinger: commit y diferencias locales, estado de migraciones, PHP, colas/cron y assets públicos. Comparar con la candidata; preparar respaldo y recuperación antes de cambios remotos.
9. Desplegar esa versión concreta y verificar reproducción, carga/entrega, correo, pagos y tareas programadas.

La unificación y el despliegue no se han ejecutado en esta comprobación. No se ha hecho push, merge, cambio de rama ni modificación de código funcional.

## Corrección de la auditoría anterior

La conclusión anterior sobre referidos era correcta para main activo, pero incompleta para todas las ramas del computador. El piloto descrito por Antigravity sí está guardado en 696e9b1, con protección de Pro y conexión a pagos. Lo pendiente es recuperar, integrar y validar ese trabajo; no necesariamente volver a programarlo desde cero.

También se encontró un protocolo histórico de Hostinger dentro del respaldo que apunta a commits antiguos (128414e y b14d79a) y se declara documento de planificación sin acceso remoto. No debe ejecutarse literalmente como si describiera la producción actual.
