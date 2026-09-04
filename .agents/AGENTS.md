# Reglas y Pautas del Proyecto

## Entorno de Producción

- El directorio de la aplicación en el servidor de producción se ha cambiado de `public_html` a la carpeta `sevenrockradio` (ubicada fuera de `public_html`) por motivos de seguridad. Ten en cuenta esta ruta cuando des instrucciones de despliegue o rutas relativas para el entorno de producción.

## Reporte y Documentación del Proyecto: Seven Rock Radio

Este documento detalla la estructura, integraciones, estética y flujo de trabajo para el proyecto "Seven Rock Radio". Está diseñado para ser agregado directamente a tu archivo `.agents/AGENTS.md` para que cualquier asistente de IA entienda tu ecosistema de inmediato sin tener que preguntarte.

---

## 🎨 Diseño y Sistema Visual (Tema "Lucille")

El proyecto utiliza **Tailwind CSS** extendido con variables nativas para lograr una estética oscura, profesional y muy orientada al rock.

**Colores Principales:**

- **Acento (Rojo Rock):** `#c32720` (Usado en botones, bordes decorativos, iconos activos y el cintillo).
- **Fondos (Oscuros):**
  - Fondo base del sitio (`body`): `#151515` o `#0a0a0b`.
  - Superficies/Tarjetas (`surface`): `#101012`.
- **Navegación:** `#081a24`.
- **Tipografías y Textos:**
  - **Títulos/Display (`font-display`):** `Oswald`, ui-sans-serif, system-ui.
  - **Cuerpo (`font-sans`):** `Open Sans`, ui-sans-serif, system-ui.
  - **Color Títulos:** `#dcdcdc` (Gris claro / Blanco tenue).
  - **Color Párrafos:** `#7b7b7b` (Gris medio).
  - **Líneas / Separadores:** `#757575` o `#1a1a1a`.

**Efectos Estándar:**

- Sombras intensas para elementos flotantes (Modal/Reproductores): `shadow-[0_28px_72px_rgba(0,0,0,.58)]`.
- Difuminado de fondo en modales (`backdrop-filter: blur()`) con fondos negros muy opacos (`bg-black` o `rgba(0,0,0,0.95)`).

## ⚙️ Integraciones y APIs (Configuradas en `.env`)

1. **Podcasts y Programas de Audio (Archive.org):** Todo el audio pesado de podcasts y programas de radio se aloja en un bucket privado de **Archive.org** (`sevenrockradio` en `us-east-1`). El sistema utiliza `ArchiveOrgService.php` para sincronizar episodios de forma dinámica.
2. **Transmisión / Radio Streaming (RadioBoss):** Integrado fuertemente con la API de RadioBoss (`https://c30.radioboss.fm/`, Estación ID `569`) para lectura de metadata, y control de la estación y horarios a través de su servidor FTP y API web.
3. **Metadatos de Música:** Se consume la API de **Discogs** y **Last.fm** para extraer imágenes, letras y datos relacionados a los álbumes y artistas.
4. **Correos Electrónicos (IMAP / SMTP):**
    - **Correos Oficiales del Sistema:** `prog.sevenrockradio@gmail.com` se utiliza para el envío SMTP y buzón principal IMAP de notificaciones y contactos comerciales.
    - **Bot de Generación de Contenido:** El sistema procesa los correos enviados por **`dark.vader.agent@gmail.com`** mediante `ProcessIncomingEmails.php` para convertirlos automáticamente en Posts del blog (categorías como "Hoy en el Rock" y "Noticias Rock"). **Todo correo proveniente de otra dirección NO será procesado como contenido automático de Dark Vader.**

## 📂 Estructura del Servidor (Hostinger)

- **Ruta de la Aplicación:** Por motivos estrictos de seguridad, todo el código fuente de Laravel (`app`, `routes`, `.env`, etc.) está alojado en una carpeta llamada **`sevenrockradio`**, la cual se encuentra **FUERA** del directorio público (`public_html`) de Hostinger.
- **Dominio Público:** Los archivos estáticos y el punto de entrada (`index.php`) se conectan a esta carpeta interna para servir la web.

## 🚀 Flujo de Trabajo (Reglas de Implementación para la IA)

Cualquier cambio de código, corrección de errores, o nueva funcionalidad **debe seguir este protocolo estricto**:

1. **Código Local a GitHub:** La IA debe realizar y testear los cambios en el entorno de desarrollo local y usar el terminal interactivo para subir los cambios al repositorio remoto (`progsevenrockradio-bot/sevenrock`).
    - Comandos internos de IA: `git add .`, `git commit -m "Descripción"`, `git push`.
2. **Comandos Entregables:** Una vez los cambios estén subidos a la rama `main`, la IA **no debe divagar** ni dar instrucciones genéricas. Debe proveer inmediatamente el bloque de comandos exacto que el usuario debe copiar y pegar en su terminal SSH de Hostinger.
3. **Bloque de Producción Estándar (Si se han compilado o modificado assets de Vite, SIEMPRE incluye el comando de copiado a public_html):**

    ```bash
    git pull
    php artisan cache:clear
    php artisan view:clear
    cp -r /home/u531780502/domains/sevenrockradio.com/sevenrockradio/public/build /home/u531780502/domains/sevenrockradio.com/public_html/
    ```

4. No se deben recomendar comandos perjudiciales como el refresco destructivo de base de datos (`migrate:fresh`) a menos que el usuario lo solicite explícitamente en desarrollo.

---

_Este bloque debe ser copiado íntegramente a tu archivo .agents/AGENTS.md para garantizar que todos los agentes futuros operen bajo este conocimiento centralizado._

- **Controles y Deslizadores (Sliders):** Para barras de volumen o de progreso de audio, se debe utilizar obligatoriamente la clase "lucille-range-slider" (que estiliza la pista plana de 4px y el thumb del color de acento) en lugar de intentar armarlas con utilidades genéricas de Tailwind, manteniendo el aspecto plano y moderno del diseño.

## ⏱️ Cron Jobs y Tareas Programadas (Hostinger)

Actualmente, el sistema solo requiere estrictamente **3 Cron Jobs** configurados en el panel de Hostinger, ya que Laravel se encarga del resto mediante su Scheduler interno. Las rutas siempre deben apuntar a la carpeta privada `sevenrockradio`.

1. **Scheduler Principal (Se encarga de procesar correos, publicar contenido y despachar tareas internas):**
   `/opt/alt/php84/usr/bin/php /home/u531780502/domains/sevenrockradio.com/sevenrockradio/artisan schedule:run`
   _(Frecuencia: Cada minuto `* * * * *`)_

2. **Cola de Marketing (Correos masivos y outreach):**
   `/opt/alt/php84/usr/bin/php /home/u531780502/domains/sevenrockradio.com/sevenrockradio/artisan queue:work --queue=marketing --stop-when-empty --tries=3 --timeout=600`
   _(Frecuencia: Cada minuto `* * * * *`)_

3. **Cola por Defecto (Procesamiento pesado de MP3 de Podcasts, Archive.org y RadioBoss):**
   `/opt/alt/php84/usr/bin/php /home/u531780502/domains/sevenrockradio.com/sevenrockradio/artisan queue:work --stop-when-empty --tries=3 --timeout=1800`
   _(Frecuencia: Cada minuto `* * * * *`)_

> **Importante para Podcasts:** Nunca alteres el tercer Cron Job. Si los podcasts se quedan en "PENDIENTE", es posible que un archivo pesado excedió el tiempo límite y haya bloqueado la cola, o el archivo `.env` no tiene definido `DB_QUEUE_RETRY_AFTER=2000`. En esos casos, basta con ejecutar `php artisan queue:restart` y `php artisan podcast:reconcile-pipeline` en el servidor para reactivarlos. No es necesario crear un 4to Cron Job.

---

## ⚡ REGLA CRÍTICA: Compilación de Assets con Vite (OBLIGATORIO)

> **Esta regla NO tiene excepciones. Incumplirla rompe el sitio en producción.**

### ¿Cuándo DEBES ejecutar `npm run build`?

Ejecutar `npm run build` en `c:\laragon\www\SevenRockRadio` es **obligatorio** ANTES de hacer `git add / commit / push` cuando hayas modificado **cualquiera** de estos archivos:

| Tipo de archivo | Ejemplos |
|---|---|
| Clases CSS de Tailwind v4 | Cualquier `.blade.php` con clases nuevas o modificadas |
| **Clases arbitrarias de Tailwind** (con corchetes `[]`) | `lg:grid-cols-[1.15fr_.85fr]`, `h-[220px]`, `text-[14px]`, etc. |
| Archivos CSS fuente | `resources/css/app.css`, `resources/css/*.css` |
| Archivos JS fuente | `resources/js/app.js`, `resources/js/*.js` |
| Componentes Blade **con clases nuevas** | Cualquier clase que no existía antes en el proyecto |

### ⚠️ La trampa más común: cambiar el breakpoint de una clase arbitraria

Tailwind v4 **solo compila las clases que detecta en el código**. Si cambias `lg:grid-cols-[1.15fr_.85fr]` a `md:grid-cols-[1.15fr_.85fr]`, esa nueva variante `md:` NO existirá en el CSS compilado hasta que hagas `npm run build`. El sitio seguirá usando el CSS anterior y la clase no tendrá efecto. **Siempre reconstruye tras cualquier cambio de clase arbitraria.**

### Flujo obligatorio cuando modificas assets:

```bash
# 1. Reconstruir el CSS/JS localmente
npm run build

# 2. Verificar que el build terminó sin errores (exit code 0)

# 3. Subir TODO (código + build generado) a GitHub
git add -A
git commit -m "Descripción del cambio"
git push origin main

# 4. Dar al usuario el bloque de producción COMPLETO con cp del build
```

### Bloque de producción COMPLETO cuando hay cambios en assets (SIEMPRE incluir el cp):

```bash
cd /home/u531780502/domains/sevenrockradio.com/sevenrockradio
git pull
php artisan cache:clear
php artisan view:clear
cp -r /home/u531780502/domains/sevenrockradio.com/sevenrockradio/public/build /home/u531780502/domains/sevenrockradio.com/public_html/
```

### Bloque de producción REDUCIDO cuando solo cambian archivos Blade/PHP (sin CSS/JS):

```bash
cd /home/u531780502/domains/sevenrockradio.com/sevenrockradio
git pull
php artisan view:clear
```

> **Regla mnemotécnica:** "¿Toqué CSS, JS, o una clase nueva de Tailwind? → `npm run build` primero, siempre."

