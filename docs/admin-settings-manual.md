# Seven Rock Radio - Manual de `admin/settings`

Este documento describe cada opcion visible en `http://127.0.0.1:8000/admin/settings`, el efecto real de cada campo y que parte del sitio consume ese valor.

## Proposito

La pantalla de settings centraliza:

- Branding global
- Tipografia y colores
- Media principal de portada
- Textos editoriales en JSON
- Contacto y notificaciones
- Redes sociales

Los valores se guardan en la tabla `theme_settings` y luego se exponen al frontend mediante `ThemeSetting::current()` y `ThemeAppearance::resolved()`.

## Reglas generales

- El formulario hace `PUT` a `admin.settings.update`.
- Las imagenes se suben como archivos y reemplazan la ruta anterior.
- Los campos JSON se validan como JSON real; si el contenido no es valido, el guardado falla.
- El cambio afecta al sitio publico sin tocar Blade ni CSS.

## Mapa por pestañas

El panel de settings esta organizado exactamente en estas tres pestañas:

1. `Apariencia y Multimedia`
2. `Contenido y Textos`
3. `Comunicaciones y Redes`

La documentacion sigue ese mismo orden para que sea mas facil cruzar cada campo con su ubicacion visual.

La vista interna del panel añade un indice lateral fijo y un boton de imprimir/guardar PDF usando la impresion del navegador.
Tambien dispone de un enlace de exportacion PDF generado en servidor desde `/admin/settings/manual/pdf`.

## Pestaña 1: Apariencia y Multimedia

### Branding

### `site_name`

- Nombre principal del sitio.
- Se usa en el titulo, en el branding y en varias vistas publicas.
- Ejemplo: `Seven Rock Radio`.

### `brand_mark`

- Texto visible del logo tipo wordmark.
- Se muestra en el header publico cuando `brand_display_mode = mark`.
- Si esta vacio, el sistema cae al nombre del sitio.

### `brand_mark_font`

- Fuente del wordmark.
- Opciones disponibles:
  - `Rock Salt`
  - `Great Vibes`
  - `Pacifico`
  - `Satisfy`
  - `Kaushan Script`
- Solo afecta al texto del branding cuando se renderiza como wordmark.

### `brand_display_mode`

- Define como se muestra la marca en el header publico.
- Valores:
  - `mark`: muestra texto/wordmark
  - `logo`: muestra la imagen subida en `logo`

### `logo`

- Imagen principal del sitio.
- Se usa en el header y en metadatos sociales cuando corresponde.
- Tipo esperado: imagen.
- Si se sube una nueva, sustituye la anterior.

### `background`

- Imagen de fondo global del sitio.
- Se usa como background visual en layouts publicos y admin.

### `hero_video_file`

- Video local para el hero principal.
- Formatos aceptados por backend:
  - `video/mp4`
  - `video/webm`
- Tamaño maximo: `102400 KB` aprox. 100 MB.
- Tiene prioridad sobre la imagen de fallback si el hero de video esta activo.

### `hero_video_url`

- URL externa para el hero principal.
- El sistema normaliza enlaces de:
  - YouTube
  - Vimeo
  - archivos directos `.mp4` o `.webm`
- Si hay URL externa valida, se usa antes que el video local.

### `hero_video_disabled`

- Desactiva el video del hero.
- Cuando esta activo, el sistema fuerza la variante de imagen.
- Sirve para apagar el video sin borrar archivos.

### Tipografia y colores

### `body_font`

- Fuente base del contenido.
- Opciones disponibles:
  - `Open Sans`
  - `Inter`
  - `Roboto`
  - `Montserrat`
  - `Poppins`

### `heading_font`

- Fuente de encabezados.
- Opciones disponibles:
  - `Oswald`
  - `Bebas Neue`
  - `Montserrat`
  - `Inter`
  - `Roboto Condensed`

### `accent_color`

- Color principal de acento.
- Se usa en botones, resaltados y elementos de marca.
- Formato requerido: hex valido, por ejemplo `#c32720`.

### `nav_color`

- Color del sistema de navegacion.
- Se usa en barras y areas de header/nav.

### `surface_color`

- Color de superficies y paneles.
- Afecta cards, bloques y contenedores oscuros.

### `body_color`

- Color del texto general.

### `heading_color`

- Color del texto de encabezados.

### `line_color`

- Color de bordes y lineas divisorias.

### Media principal

### `hero_slide_primary`

- Primera imagen del hero / slider.
- Normalmente la imagen mas visible de entrada.

### `hero_slide_secondary`

- Segunda imagen del hero / slider.
- Se usa como alternativa o fallback visual.

### `home_album_cover`

- Portada destacada de album para la home.

### `home_video_image`

- Imagen destacada para la seccion de video en home.

## Pestaña 2: Contenido y Textos

Estos campos son JSON. No se editan como texto libre, sino como estructuras JSON.

### `featured_stories_json`

- Controla el bloque de historias destacadas.
- Incluye titulo, subtitulo, historia destacada y lista de historias.

### `latest_podcasts_json`

- Controla el bloque de ultimos podcasts.
- Incluye episodio destacado y listado de episodios.

### `home_headings_json`

- Controla los titulos y subtitulos de los bloques de portada.
- Sirve para reescribir textos editoriales sin tocar vistas.

### `ui_texts_json`

- Contiene textos reutilizables de la UI publica.
- Ejemplos:
  - `read_more`
  - `submit`
  - `search_placeholder`
  - `related_products`

### `admin_texts_json`

- Contiene textos reutilizables del panel admin.
- Afecta titulos, botones, labels y mensajes del panel.
- Ejemplos:
  - `dashboard_title`
  - `theme_settings`
  - `login_button`
  - `save_settings`

## Pestaña 3: Comunicaciones y Redes

### `contact_form_title`

- Titulo del formulario de contacto publico.

### `contact_info_title`

- Titulo del bloque de informacion de contacto.

### `contact_description`

- Texto descriptivo del area de contacto.

### `contact_address`

- Direccion fisica o postal.

### `contact_email`

- Email publico de contacto.

### `notification_email`

- Email principal para notificaciones.
- Es el primer destino cuando el sistema busca un correo activo.

### `notification_copy_email`

- Correo en copia para notificaciones globales.
- El sistema lo usa como copia por defecto en programas maestros.

### `notification_from_email`

- Email remitente para notificaciones.

### `notification_reply_to_email`

- Email usado como `Reply-To` en correos de salida.

### `notification_mailer`

- Sobrescribe el mailer usado por notificaciones.
- Si esta vacio, usa la configuracion por defecto de Laravel.

### `contact_phone_primary`

- Telefono principal visible en el sitio.

### `contact_phone_secondary`

- Telefono secundario visible en el sitio.

### Estado activo de notificaciones

El panel muestra una caja de estado con:

- Correo principal activo
- Correo copia activo
- Remitente activo
- Reply-To activo
- Mailer activo

Eso no se edita ahi directamente; es un resumen del valor que el sistema usara ahora mismo.

### Contacto y notificaciones

### `social_facebook`

- Enlace publico a Facebook.

### `social_instagram`

- Enlace publico a Instagram.

### `social_youtube`

- Enlace publico a YouTube.

### `social_tiktok`

- Enlace publico a TikTok.

### `social_x`

- Enlace publico a X.

Las redes vacias no se muestran en el footer publico.

### Redes sociales

### `Save settings`

- Guarda todos los cambios.
- Si un campo falla validacion, no persiste nada.

### `Back to dashboard`

- Vuelve al dashboard del admin sin guardar.

## Acciones del formulario

### Branding del hero

Orden real que usa la app:

1. `hero_video_disabled = true` fuerza imagen.
2. `hero_video_url` valida se usa como embed externo.
3. `hero_video_file` local se usa como video interno.
4. Si no hay nada, cae al fallback de imagen.

### Fuente y colores

El frontend no lee estos campos directamente del formulario; los consume via `ThemeAppearance::resolved()`.

## Prioridad real de rendering

- Usa `brand_display_mode = logo` solo si subiste logo nuevo.
- Mantén `accent_color`, `nav_color` y `surface_color` con contraste suficiente.
- No pegues JSON malformado en los campos editoriales.
- Si cambias `notification_mailer`, verifica que exista en `config/mail.php`.
- Si subes archivos grandes, valida primero que el servidor acepte ese peso.

## Recomendaciones operativas

- `admin_texts_json`
- `ui_texts_json`
- `featured_stories_json`
- `latest_podcasts_json`
- `home_headings_json`
- `accent_color`
- `brand_display_mode`
- `hero_video_url`

## Campos que suelen romper el sitio si se editan mal

- Branding: nombre, logo, marca y fondo.
- Typography: fuentes y colores globales.
- Media: slides, portada de album y video.
- Editorial: textos en JSON para home y admin.
- Contacto: emails y telefonos.
- Social: links del footer.

## Resumen corto para operacion

| Campo | Qué hace | Dónde se ve | Riesgo si se toca mal |
|---|---|---|---|
| `site_name` | Nombre global del sitio | Títulos, branding, metadatos | Bajo |
| `brand_mark` | Texto del logo tipo wordmark | Header público | Bajo |
| `brand_mark_font` | Fuente del wordmark | Header público | Bajo |
| `brand_display_mode` | Decide si se ve texto o logo | Header público | Medio |
| `logo` | Logo principal | Header y redes sociales | Medio |
| `background` | Fondo general | Layout público y admin | Medio |
| `hero_video_file` | Video local del hero | Hero principal | Medio |
| `hero_video_url` | Video externo del hero | Hero principal | Alto si la URL es inválida |
| `hero_video_disabled` | Apaga el video del hero | Hero principal | Medio |
| `body_font` | Fuente base | Texto general | Bajo |
| `heading_font` | Fuente de encabezados | Títulos y secciones | Bajo |
| `accent_color` | Color de acento | Botones y highlights | Medio |
| `nav_color` | Color de navegación | Barras y cabeceras | Medio |
| `surface_color` | Color de superficies | Cards y paneles | Bajo |
| `body_color` | Color del texto base | Texto general | Bajo |
| `heading_color` | Color de títulos | Encabezados | Bajo |
| `line_color` | Color de bordes | Separadores y líneas | Bajo |
| `hero_slide_primary` | Primera imagen del hero | Home principal | Bajo |
| `hero_slide_secondary` | Segunda imagen del hero | Home principal | Bajo |
| `home_album_cover` | Portada destacada de álbum | Home | Bajo |
| `home_video_image` | Imagen destacada del video | Home | Bajo |
| `featured_stories_json` | Configura historias destacadas | Home | Alto si el JSON se rompe |
| `latest_podcasts_json` | Configura el bloque de podcasts | Home | Alto si el JSON se rompe |
| `home_headings_json` | Títulos editoriales del home | Home | Alto si el JSON se rompe |
| `ui_texts_json` | Textos UI del sitio | Varias vistas públicas | Alto si el JSON se rompe |
| `admin_texts_json` | Textos del panel | Panel admin | Alto si el JSON se rompe |
| `contact_form_title` | Título del formulario | Página de contacto | Bajo |
| `contact_info_title` | Título del bloque de info | Página de contacto | Bajo |
| `contact_description` | Texto descriptivo | Página de contacto | Bajo |
| `contact_address` | Dirección | Página de contacto | Bajo |
| `contact_email` | Email público | Contacto y pie | Bajo |
| `notification_email` | Email principal de notificación | Correos del sistema | Medio |
| `notification_copy_email` | Email copia | Correos del sistema | Medio |
| `notification_from_email` | Remitente | Correos del sistema | Medio |
| `notification_reply_to_email` | Reply-To | Correos del sistema | Medio |
| `notification_mailer` | Mailer activo | Envío de correos | Alto si el mailer no existe |
| `contact_phone_primary` | Teléfono principal | Contacto | Bajo |
| `contact_phone_secondary` | Teléfono secundario | Contacto | Bajo |
| `social_facebook` | URL de Facebook | Footer | Bajo |
| `social_instagram` | URL de Instagram | Footer | Bajo |
| `social_youtube` | URL de YouTube | Footer | Bajo |
| `social_tiktok` | URL de TikTok | Footer | Bajo |
| `social_x` | URL de X | Footer | Bajo |

## Tabla visual de campos

Si solo vas a tocar lo imprescindible, sigue este orden:

1. `site_name`
2. `brand_display_mode`
3. `logo`
4. `background`
5. `accent_color`
6. `nav_color`
7. `body_font` y `heading_font`
8. `contact_email` y `contact_phone_primary`
9. `social_*`

No edites los campos JSON si no sabes exactamente la estructura.

## Versión corta para administradores no técnicos

Este orden reduce errores visuales y evita que el sitio quede desalineado:

1. Branding base
2. Colores globales
3. Tipografía
4. Media principal
5. Contacto y notificaciones
6. Redes sociales
7. Textos JSON del home
8. Textos JSON del admin

## Orden recomendado de edición

## Capturas sugeridas para el manual

Si vas a documentarlo con imágenes, las capturas útiles son estas:

1. Pantalla completa de `admin/settings` con todos los bloques visibles.
2. Bloque de Branding abierto.
3. Bloque de Typography & colors.
4. Bloque de Main media.
5. Bloque de Home editorial con los JSON.
6. Bloque de Contacto y notificaciones.
7. Bloque de Social links.
8. Ejemplo del sitio público antes y después de cambiar `brand_display_mode`.
9. Ejemplo del footer antes y después de cambiar `social_*`.
10. Ejemplo del header antes y después de cambiar `accent_color` y `nav_color`.

## Vista imprimible

La pagina interna de `/admin/settings/manual` incluye un modo de impresion que oculta la navegacion lateral y los botones de accion.
Desde ahi puedes usar `Imprimir / Guardar PDF` para exportar el manual sin necesidad de una herramienta externa.
