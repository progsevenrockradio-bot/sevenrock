# Manual de Uso: Creación de Artículos (Posts)
**Ruta:** `/admin/posts/create`

Este documento describe detalladamente el funcionamiento y el uso de la interfaz de creación y edición de posts en el panel de administración de Seven Rock Radio.

---

## 📋 1. Campos Generales de Metadatos

En la parte superior del formulario se encuentran los metadatos principales del post:

*   **Título:** Título del post. Es obligatorio.
*   **Slug:** La parte de la URL amigable (ej: `nuevo-album-de-green-day`). Si se deja vacío, el sistema lo generará automáticamente a partir del título al guardar.
*   **Autor:** Nombre del autor del artículo. Por defecto se establece como `admin`.
*   **Fecha de Publicación:** Campo de fecha y hora local.
    *   *Importante:* Te permite programar posts en el futuro (ver sección de "Acciones de Guardado").

---

## 🧱 2. Editor Interactivo por Bloques
El sistema cuenta con un potente editor visual por bloques (desarrollado con Alpine.js) que emula las estructuras de bloques de WordPress. Puedes añadir, reordenar y eliminar bloques dinámicamente.

### Botones de Bloques Disponibles:
En la barra de herramientas puedes pulsar para añadir cualquiera de estos bloques:

1.  **Paragraph (Párrafo):**
    *   Para redactar el texto general del artículo.
    *   **Enlaces Internos (Inline Links):** Puedes seleccionar palabras específicas y agregarles un enlace (URL). El primer término coincidente en el texto se convertirá en un enlace cliqueable automáticamente en el frontend.
2.  **Heading (Encabezado):**
    *   Para estructurar el artículo con títulos internos (subtítulos).
3.  **Quote (Cita):**
    *   Para destacar frases célebres o citas. Permite ingresar el texto de la cita y la fuente/autor (Citation).
4.  **Image (Imagen):**
    *   Para insertar una imagen dentro del post. Puedes:
        *   Escribir directamente una **URL de imagen** externa.
        *   Subir un archivo desde tu equipo pulsando **"Upload image"** (se subirá automáticamente a los servidores en segundo plano y rellenará la URL).
5.  **Gallery (Galería):**
    *   Permite agrupar múltiples fotos. Puedes pulsar **"Upload images"** y seleccionar varios archivos a la vez. También puedes añadir imágenes vacías y asignarles URLs manualmente.
6.  **Raw HTML (HTML Puro):**
    *   Para insertar códigos incrustados personalizados (ej: reproductores de Spotify, Soundcloud, Bandcamp o videos de YouTube embebidos).

*   **Ordenamiento:** Cada bloque cuenta con flechas de ordenación (`↑` y `↓`) para subir o bajar su posición en el artículo, y un botón de **Delete** para eliminarlo.
*   **Live Preview:** En la columna derecha de cada bloque verás una previsualización en tiempo real de cómo lucirá ese bloque en la web.

---

## 🏷️ 3. Taxonomías (Categorías y Etiquetas)

*   **Categorías:** Campo de texto separado por comas (ej: `Noticias, Conciertos, Rock`). Cuenta con autocompletado inteligente a partir de las categorías ya existentes en el sistema.
*   **Etiquetas (Tags):** Palabras clave para organizar el contenido (ej: `live, green day, metal, 2026`), también autocompletadas.

---

## 🖼️ 4. Imagen Destacada (Featured Image)

Es la imagen principal que aparece en el listado del blog y en la cabecera del artículo. Tienes dos formas de ingresarla (se requiere al menos una):
1.  **URL de Imagen Destacada:** Ruta de texto a una imagen existente o externa.
2.  **Subir archivo de Imagen Destacada:** Sube la foto directamente desde tu dispositivo. El sistema la procesará y optimizará en la carpeta `catalog/posts`.

---

## 🌐 5. Redes del Artista, Enlaces Externos e Información

Campos opcionales para enriquecer el artículo:
*   **Redes del Artista / Fuente:** Enlaces directos a las páginas oficiales de Facebook, Instagram, Twitter y YouTube de la banda o artista mencionado.
*   **Enlace Externo y Créditos:**
    *   *External link URL / Label:* Para dirigir al lector a una tienda externa o reproductor (ej: comprar boletos, escuchar en Spotify).
    *   *Source name / URL:* Para dar créditos al autor original de la noticia si fue tomada de otro medio.

---

## 🔍 6. Optimización SEO

*   **Meta Title:** Título específico para los motores de búsqueda (Google, Bing). Límite sugerido de 120 caracteres.
*   **Meta Description:** Resumen del post que aparece en los resultados de búsqueda. Ayuda a mejorar el posicionamiento.

---

## 💾 7. Acciones de Guardado (Botones de Envío)

El formulario de pie cuenta con tres acciones inteligentes:

1.  **📢 Publicar (Publish):** Guarda el artículo y lo publica inmediatamente (activo en la web).
2.  **📝 Guardar Borrador (Save Draft):** Guarda toda la información pero mantiene el post oculto (estado *Borrador*).
3.  **⏳ Programar (Schedule):** Si en el campo **Fecha de Publicación** has seleccionado una fecha y hora en el futuro, este botón se activará y te permitirá programar el post para que se publique automáticamente en la fecha seleccionada.
