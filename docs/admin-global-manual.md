# Manual General del Panel de Administración - Seven Rock Radio

Este manual detalla el funcionamiento, los conceptos clave y el uso de cada sección disponible en el panel de administración de Seven Rock Radio (`/admin`).

---

## 📊 1. Dashboard (Panel Principal)
El Dashboard es el centro de control operacional donde se visualizan métricas en tiempo real, el estado del pipeline de podcasts, la gestión rápida de taxonomías y la actividad de outreach.

*   **Métricas Generales:** Estadísticas rápidas sobre usuarios, artistas de radio, canciones, programas master y contactos.
*   **Podcast Pipeline:** Monitoriza el flujo de procesamiento de audios subidos. Muestra cuántos episodios están procesándose, pendientes de sincronización en RadioBOSS, pendientes de subida a Archive.org o verificados.
*   **Taxonomías Rápidas:** Permite crear y eliminar categorías y etiquetas directamente desde la página de inicio.
*   **Resumen de Convocatorias (Outreach):** Listado de campañas recientes lanzadas a bandas y últimos contactos registrados.

---

## 📝 2. Posts (Artículos)
Sección destinada a redactar y publicar las noticias, artículos y reseñas de la emisora.

*   **Campos de Metadatos:** Título, Slug (URL amigable), Autor y Fecha de Publicación (admite horas futuras para programación diferida).
*   **Editor por Bloques:** Sistema dinámico para añadir Párrafos, Encabezados, Citas, Imágenes, Galerías y bloques de HTML Puro (para reproductores como Spotify o Bandcamp).
*   **Inline Links:** En los párrafos, se pueden enlazar palabras específicas de forma inteligente ingresando el término y la URL de destino.
*   **Acciones de Envío:**
    *   *Publicar:* El artículo se hace público en la web inmediatamente.
    *   *Guardar Borrador:* Se guarda la información de forma privada.
    *   *Programar:* Si la fecha de publicación es futura, el sistema publicará el artículo automáticamente al cumplirse la hora.

---

## 💬 3. Comentarios
Permite moderar la interacción y opiniones de los oyentes en los artículos publicados.

*   **Aprobar / Desaprobar:** Los comentarios nuevos pueden filtrarse antes de mostrarse en la web principal.
*   **Editar/Eliminar:** Permite corregir erratas o spam, y limpiar el historial eliminando mensajes inapropiados.

---

## 📅 4. Eventos (Conciertos y Coberturas)
Gestión de la agenda cultural, conciertos y coberturas especiales de Seven Rock Radio.

*   **Eventos Próximos (Upcoming):** Publicación de fechas, locales y flyers de conciertos futuros.
*   **Eventos Pasados (Past):** Galería o notas sobre conciertos cubiertos por la radio.
*   **Evento Único:** Plantilla dedicada a destacar un único gran evento de la temporada.

---

## 🎙️ 5. Programas Master
Define los programas o podcasts oficiales que forman la parrilla de programación de la radio.

*   **Estructura Base:** Define el nombre del programa, el locutor a cargo, y la descripción general. Es el pilar sobre el cual se asocian posteriormente los episodios individuales en "Podcast Uploads".

---

## 📤 6. Podcast Uploads (Subida de Episodios)
Herramienta crítica para subir archivos de audio MP3 y distribuirlos en los canales y plataformas de la radio.

*   **Formulario de Subida:** Requiere asociar el audio a un Programa Master, ingresar un título de episodio y cargar el archivo MP3.
*   **Opciones de Distribución:**
    *   *Sincronización con Archive.org:* Sube automáticamente el archivo a la biblioteca de Archive.org para respaldo y distribución pública.
    *   *RadioBOSS Pipeline:* Prepara el audio para ser incorporado en el software de automatización de la radio.

---

## 🎵 7. Canciones (Pistas y Audios)
Catálogo musical e informativo de las canciones reproducidas en la radio.

*   **Campos:** Título de la pista, artista, archivo de audio y vinculación opcional a un álbum.
*   **Utilidad:** Alimenta el reproductor web y los historiales de música al aire.

---

## 💿 8. Álbumes (Discografía)
Administración de los álbumes y producciones discográficas de las bandas.

*   **Ficha del Álbum:** Título, año de lanzamiento, discográfica, carátula y listado de canciones asociadas.

---

## 🎥 9. Videos
Sección para compartir videoclips, entrevistas y sesiones en vivo de las bandas de rock.

*   **Embebido Rápido:** Soporta el ingreso de enlaces directos de plataformas como YouTube o Vimeo para generar el reproductor en la web.

---

## 📷 10. Galería de Fotos
Álbumes fotográficos de eventos, sesiones en la cabina de la radio y coberturas de festivales.

*   **Carga Múltiple:** Sistema optimizado para subir colecciones enteras de imágenes a carpetas del servidor y organizarlas por carpetas o álbumes visuales.

---

## 🎸 11. Artistas de Radio (Band Profiles)
Fichas técnicas y de perfil de las agrupaciones de rock asociadas o reproducidas.

*   **Generador Automático:** Cuenta con una herramienta para autogenerar biografías breves.
*   **Enlaces Oficiales:** Conexión a perfiles de redes sociales de las bandas y listados de discografías.

---

## 👥 12. Talentos (Locutores y Staff)
Gestión de los perfiles públicos y accesos del staff de locutores y talentos de voz.

*   **Estado:** Permite activar o suspender locutores del staff público.
*   **Media y Portafolio:** Repositorio donde se alojan demos de voz y material promocional de cada locutor.

---

## 🔑 13. Programas Convocatoria
Herramienta de control de postulaciones y códigos de invitación para nuevos programas.

*   **Códigos de Registro:** Generación de invitaciones únicas para que nuevos locutores externos se registren e inicien su propio espacio.

---

## ✉️ 14. Outreach (Convocatoria de Bandas)
Gestión de relaciones públicas y campañas de correo electrónico para reclutar bandas o patrocinadores.

*   **Plantillas (Templates):** Redacción de plantillas de correos personalizadas.
*   **Campañas:** Envío masivo programado a listas de contactos y seguimiento de métricas de entrega.
*   **Contactos:** Base de datos de correos electrónicos de managers, bandas e integrantes del medio.

---

## ⚙️ 15. Configuración del Sistema
Ajustes globales del sitio web, apariencia y seguridad (Acceso limitado a Super Administradores).

*   **Apariencia:** Cambia el logotipo oficial, fondos de pantalla, fuentes y el color de acento principal del tema.
*   **Seguridad:** Requiere re-introducción de contraseña para confirmar cambios (middleware `password.confirm`).
*   **Bitácora de Auditoría:** Historial detallado de todas las acciones críticas realizadas por los administradores en el panel (creación, edición y borrado de elementos).
