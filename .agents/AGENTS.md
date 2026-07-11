# Reglas y Pautas del Proyecto

## Entorno de Producción
- El directorio de la aplicación en el servidor de producción se ha cambiado de `public_html` a la carpeta `sevenrockradio` (ubicada fuera de `public_html`) por motivos de seguridad. Ten en cuenta esta ruta cuando des instrucciones de despliegue o rutas relativas para el entorno de producción.
# Reporte y Documentación del Proyecto: Seven Rock Radio

Este documento detalla la estructura, integraciones, estética y flujo de trabajo para el proyecto "Seven Rock Radio". Está diseñado para ser agregado directamente a tu archivo `.agents/AGENTS.md` para que cualquier asistente de IA entienda tu ecosistema de inmediato sin tener que preguntarte.

---

## 🎨 Diseño y Sistema Visual (Tema "Lucille")

El proyecto utiliza **Tailwind CSS** extendido con variables nativas para lograr una estética oscura, profesional y muy orientada al rock.

**Colores Principales:**
*   **Acento (Rojo Rock):** `#c32720` (Usado en botones, bordes decorativos, iconos activos y el cintillo).
*   **Fondos (Oscuros):**
    *   Fondo base del sitio (`body`): `#151515` o `#0a0a0b`.
    *   Superficies/Tarjetas (`surface`): `#101012`.
*   **Navegación:** `#081a24`.
*   **Tipografías y Textos:**
    *   **Títulos/Display (`font-display`):** `Oswald`, ui-sans-serif, system-ui.
    *   **Cuerpo (`font-sans`):** `Open Sans`, ui-sans-serif, system-ui.
    *   **Color Títulos:** `#dcdcdc` (Gris claro / Blanco tenue).
    *   **Color Párrafos:** `#7b7b7b` (Gris medio).
    *   **Líneas / Separadores:** `#757575` o `#1a1a1a`.

**Efectos Estándar:**
*   Sombras intensas para elementos flotantes (Modal/Reproductores): `shadow-[0_28px_72px_rgba(0,0,0,.58)]`.
*   Difuminado de fondo en modales (`backdrop-filter: blur()`) con fondos negros muy opacos (`bg-black` o `rgba(0,0,0,0.95)`).

## ⚙️ Integraciones y APIs (Configuradas en `.env`)

1.  **Podcasts y Programas de Audio (Archive.org):** 
    Todo el audio pesado de podcasts y programas de radio se aloja en un bucket privado de **Archive.org** (`sevenrockradio` en `us-east-1`). El sistema utiliza `ArchiveOrgService.php` para sincronizar episodios de forma dinámica.
2.  **Transmisión / Radio Streaming (RadioBoss):** 
    Integrado fuertemente con la API de RadioBoss (`https://c30.radioboss.fm/`, Estación ID `569`) para lectura de metadata, y control de la estación y horarios a través de su servidor FTP y API web.
3.  **Metadatos de Música:** 
    Se consume la API de **Discogs** y **Last.fm** para extraer imágenes, letras y datos relacionados a los álbumes y artistas.
4.  **Correos Electrónicos (IMAP / SMTP):**
    *   **Correos Oficiales del Sistema:** `prog.sevenrockradio@gmail.com` se utiliza para el envío SMTP y buzón principal IMAP de notificaciones y contactos comerciales.
    *   **Bot de Generación de Contenido:** El sistema procesa los correos enviados por **`dark.vader.agent@gmail.com`** mediante `ProcessIncomingEmails.php` para convertirlos automáticamente en Posts del blog (categorías como "Hoy en el Rock" y "Noticias Rock"). **Todo correo proveniente de otra dirección NO será procesado como contenido automático de Dark Vader.**

## 📂 Estructura del Servidor (Hostinger)

*   **Ruta de la Aplicación:** Por motivos estrictos de seguridad, todo el código fuente de Laravel (`app`, `routes`, `.env`, etc.) está alojado en una carpeta llamada **`sevenrockradio`**, la cual se encuentra **FUERA** del directorio público (`public_html`) de Hostinger.
*   **Dominio Público:** Los archivos estáticos y el punto de entrada (`index.php`) se conectan a esta carpeta interna para servir la web. 

## 🚀 Flujo de Trabajo (Reglas de Implementación para la IA)

Cualquier cambio de código, corrección de errores, o nueva funcionalidad **debe seguir este protocolo estricto**:

1.  **Código Local a GitHub:** La IA debe realizar y testear los cambios en el entorno de desarrollo local y usar el terminal interactivo para subir los cambios al repositorio remoto (`progsevenrockradio-bot/sevenrock`).
    *   Comandos internos de IA: `git add .`, `git commit -m "Descripción"`, `git push`.
2.  **Comandos Entregables:** Una vez los cambios estén subidos a la rama `main`, la IA **no debe divagar** ni dar instrucciones genéricas. Debe proveer inmediatamente el bloque de comandos exacto que el usuario debe copiar y pegar en su terminal SSH de Hostinger.
3.  **Bloque de Producción Estándar:**
    ```bash
    git pull
    php artisan cache:clear
# Reporte y Documentación del Proyecto: Seven Rock Radio

Este documento detalla la estructura, integraciones, estética y flujo de trabajo para el proyecto "Seven Rock Radio". Está diseñado para ser agregado directamente a tu archivo `.agents/AGENTS.md` para que cualquier asistente de IA entienda tu ecosistema de inmediato sin tener que preguntarte.

---

## 🎨 Diseño y Sistema Visual (Tema "Lucille")

El proyecto utiliza **Tailwind CSS** extendido con variables nativas para lograr una estética oscura, profesional y muy orientada al rock.

**Colores Principales:**
*   **Acento (Rojo Rock):** `#c32720` (Usado en botones, bordes decorativos, iconos activos y el cintillo).
*   **Fondos (Oscuros):**
    *   Fondo base del sitio (`body`): `#151515` o `#0a0a0b`.
    *   Superficies/Tarjetas (`surface`): `#101012`.
*   **Navegación:** `#081a24`.
*   **Tipografías y Textos:**
    *   **Títulos/Display (`font-display`):** `Oswald`, ui-sans-serif, system-ui.
    *   **Cuerpo (`font-sans`):** `Open Sans`, ui-sans-serif, system-ui.
    *   **Color Títulos:** `#dcdcdc` (Gris claro / Blanco tenue).
    *   **Color Párrafos:** `#7b7b7b` (Gris medio).
    *   **Líneas / Separadores:** `#757575` o `#1a1a1a`.

**Efectos Estándar:**
*   Sombras intensas para elementos flotantes (Modal/Reproductores): `shadow-[0_28px_72px_rgba(0,0,0,.58)]`.
*   Difuminado de fondo en modales (`backdrop-filter: blur()`) con fondos negros muy opacos (`bg-black` o `rgba(0,0,0,0.95)`).

## ⚙️ Integraciones y APIs (Configuradas en `.env`)

1.  **Podcasts y Programas de Audio (Archive.org):** 
    Todo el audio pesado de podcasts y programas de radio se aloja en un bucket privado de **Archive.org** (`sevenrockradio` en `us-east-1`). El sistema utiliza `ArchiveOrgService.php` para sincronizar episodios de forma dinámica.
2.  **Transmisión / Radio Streaming (RadioBoss):** 
    Integrado fuertemente con la API de RadioBoss (`https://c30.radioboss.fm/`, Estación ID `569`) para lectura de metadata, y control de la estación y horarios a través de su servidor FTP y API web.
3.  **Metadatos de Música:** 
    Se consume la API de **Discogs** y **Last.fm** para extraer imágenes, letras y datos relacionados a los álbumes y artistas.
4.  **Correos Electrónicos (IMAP / SMTP):**
    *   **Correos Oficiales del Sistema:** `prog.sevenrockradio@gmail.com` se utiliza para el envío SMTP y buzón principal IMAP de notificaciones y contactos comerciales.
    *   **Bot de Generación de Contenido:** El sistema procesa los correos enviados por **`dark.vader.agent@gmail.com`** mediante `ProcessIncomingEmails.php` para convertirlos automáticamente en Posts del blog (categorías como "Hoy en el Rock" y "Noticias Rock"). **Todo correo proveniente de otra dirección NO será procesado como contenido automático de Dark Vader.**

## 📂 Estructura del Servidor (Hostinger)

*   **Ruta de la Aplicación:** Por motivos estrictos de seguridad, todo el código fuente de Laravel (`app`, `routes`, `.env`, etc.) está alojado en una carpeta llamada **`sevenrockradio`**, la cual se encuentra **FUERA** del directorio público (`public_html`) de Hostinger.
*   **Dominio Público:** Los archivos estáticos y el punto de entrada (`index.php`) se conectan a esta carpeta interna para servir la web. 

## 🚀 Flujo de Trabajo (Reglas de Implementación para la IA)

Cualquier cambio de código, corrección de errores, o nueva funcionalidad **debe seguir este protocolo estricto**:

1.  **Código Local a GitHub:** La IA debe realizar y testear los cambios en el entorno de desarrollo local y usar el terminal interactivo para subir los cambios al repositorio remoto (`progsevenrockradio-bot/sevenrock`).
    *   Comandos internos de IA: `git add .`, `git commit -m "Descripción"`, `git push`.
2.  **Comandos Entregables:** Una vez los cambios estén subidos a la rama `main`, la IA **no debe divagar** ni dar instrucciones genéricas. Debe proveer inmediatamente el bloque de comandos exacto que el usuario debe copiar y pegar en su terminal SSH de Hostinger.
3.  **Bloque de Producción Estándar:**
    ```bash
    git pull
    php artisan cache:clear
    php artisan view:clear
    ```
4.  No se deben recomendar comandos perjudiciales como el refresco destructivo de base de datos (`migrate:fresh`) a menos que el usuario lo solicite explícitamente en desarrollo.

---
*Este bloque debe ser copiado íntegramente a tu archivo .agents/AGENTS.md para garantizar que todos los agentes futuros operen bajo este conocimiento centralizado.*
*   **Controles y Deslizadores (Sliders):** Para barras de volumen o de progreso de audio, se debe utilizar obligatoriamente la clase "lucille-range-slider" (que estiliza la pista plana de 4px y el thumb del color de acento) en lugar de intentar armarlas con utilidades genericas de Tailwind, manteniendo el aspecto plano y moderno del diseno.

## ⏱️ Cron Jobs y Tareas Programadas (Hostinger)
Actualmente, el sistema solo requiere estrictamente **3 Cron Jobs** configurados en el panel de Hostinger, ya que Laravel se encarga del resto mediante su Scheduler interno. Las rutas siempre deben apuntar a la carpeta privada `sevenrockradio`.

1. **Scheduler Principal (Se encarga de procesar correos, publicar contenido y despachar tareas internas):**
   `/opt/alt/php84/usr/bin/php /home/u531780502/domains/sevenrockradio.com/sevenrockradio/artisan schedule:run >> /dev/null 2>&1`
   *(Frecuencia: Cada minuto `* * * * *`)*

2. **Cola de Marketing (Correos masivos y outreach):**
   `/opt/alt/php84/usr/bin/php /home/u531780502/domains/sevenrockradio.com/sevenrockradio/artisan queue:work --queue=marketing --stop-when-empty --tries=3 --timeout=600 >> /dev/null 2>&1`
   *(Frecuencia: Cada minuto `* * * * *`)*

3. **Cola por Defecto (Procesamiento pesado de MP3 de Podcasts, Archive.org y RadioBoss):**
   `/opt/alt/php84/usr/bin/php /home/u531780502/domains/sevenrockradio.com/sevenrockradio/artisan queue:work --stop-when-empty --tries=3 --timeout=1800 >> /dev/null 2>&1`
   *(Frecuencia: Cada minuto `* * * * *`)*

> **Importante para Podcasts:** Nunca alteres el tercer Cron Job. Si los podcasts se quedan en "PENDIENTE", es posible que un archivo pesado excedió el tiempo límite y haya bloqueado la cola, o el archivo `.env` no tiene definido `DB_QUEUE_RETRY_AFTER=2000`. En esos casos, basta con ejecutar `php artisan queue:restart` y `php artisan podcast:reconcile-pipeline` en el servidor para reactivarlos. No es necesario crear un 4to Cron Job.
