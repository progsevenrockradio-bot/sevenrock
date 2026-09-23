<?php

declare(strict_types=1);

namespace App\Support;

class AiPrompts
{
    public static function getParsePrompt(string $subject, string $body): string
    {
        return <<<PROMPT
Eres un asistente de redacción editorial para la radio de rock "Seven Rock Radio".
Tu tarea es analizar el correo electrónico recibido (asunto y cuerpo) y convertirlo en contenido estructurado para la web.

El correo puede tratar sobre un "Nuevo Lanzamiento" de un disco/sencillo/video de una banda de rock, o bien ser una noticia general/artículo para el "Blog" (Post).

Sigue estas reglas estrictas:
1. Identifica el tipo de contenido ("type"):
   - "release": Si habla de un nuevo disco, EP, single, videoclip o canción recién lanzada por una banda/artista de rock/metal.
   - "post": Si es una noticia de música, crónica de concierto, artículo de opinión o texto informativo general relevante sobre rock/metal.
   - "discard": Si es correo no deseado (spam), promociones o publicidad pagada de agencias de relaciones públicas sobre sus planes/servicios, correos personales sin información musical, o cualquier otra cosa que no sea de interés periodístico sobre artistas o bandas de rock.
2. Evalúa la importancia/relevancia del correo para la audiencia de la radio ("importance"): un número entero del 1 al 5 (donde 5 es de importancia crítica como lanzamientos o noticias de bandas muy reconocidas, 3-4 es para lanzamientos y noticias normales del género, 2 es para comunicados poco interesantes o periféricos, y 1 es para publicidad descartada o irrelevante).
3. Limpia el texto de firmas de correo, saludos iniciales (ej. "Hola Seven Rock Radio"), despedidas e información de contacto del email.
4. Traduce o reescribe el contenido al español con un tono periodístico, profesional, emocionante y con alta calidad gramatical (propio de una revista de rock).
5. Si es "release", identifica y separa el "artist_name" (Nombre de la banda/artista) y el "title" (Nombre de la canción o disco).
6. Si es "post", identifica y separa el "title" (un titular atractivo en español para el post).
7. Extrae los siguientes enlaces si se encuentran en el texto (deben ser URLs completas válidas):
   - "youtube_url": Enlace a un video de YouTube.
   - "spotify_url": Enlace a Spotify.
   - "facebook_url": Enlace a una página o publicación de Facebook.
   - "instagram_url": Enlace a Instagram.
   - "twitter_url": Enlace a Twitter/X.
8. Genera un "excerpt" (resumen corto de 150-180 caracteres) y el "content" (el cuerpo principal limpio y bien redactado, separado por párrafos con salto de línea doble). Si el correo es "discard", puedes poner texto genérico de descarte en estos campos.

Devuelve la respuesta estrictamente en formato JSON utilizando el esquema indicado.

Asunto del correo: {$subject}
Cuerpo del correo:
{$body}
PROMPT;
    }

    public static function getContactPrompt(string $subject, string $body): string
    {
        return <<<PROMPT
Analiza el correo electrónico recibido (asunto y cuerpo) y extrae información sobre la persona de contacto, la empresa o banda de rock y su cargo/rol.

Queremos identificar:
1. "name": El nombre real de la persona que escribe o firma el correo (ej: "Brian Heason"). Si no se encuentra un nombre de pila claro, pon el nombre de pila más probable o el alias del remitente.
2. "company_or_band": El nombre de la empresa de relaciones públicas (PR), agencia de prensa, banda de música, sello discográfico u organización que representan (ej: "HBM Promotions", "Metal Devastation PR"). Si no se menciona ninguna empresa o banda, pon "Independiente" o la banda descrita en el asunto.
3. "role": El cargo, puesto o rol del contacto en esa empresa/banda (ej: "Music Plugger", "Publicist", "Manager", "Vocalist", "Contacto de Prensa"). Si no figura un rol explícito, dedúcelo según el tono (ej. "Representante" o "Músico").

Presta especial atención a la firma al final del correo, que suele contener el nombre de la persona, su empresa, enlaces de redes sociales y su rol exacto.

Devuelve la respuesta estrictamente en formato JSON utilizando el esquema indicado.

Asunto del correo: {$subject}
Cuerpo del correo:
{$body}
PROMPT;
    }

    public static function getEfemeridesPrompt(string $subject, string $body): string
    {
        return <<<PROMPT
Eres un asistente de redacción editorial para la radio de rock "Seven Rock Radio".
Tu tarea es analizar el correo electrónico recibido que contiene una o varias "Efemérides" (Hoy en el Rock) y convertirlo en un arreglo estructurado.

Sigue estas reglas estrictas:
1. Extrae cada efeméride individual que encuentres en el texto.
2. Cada efeméride debe tener:
   - "title": Un título atractivo en español (ej: "Se lanza Master of Puppets de Metallica").
   - "excerpt": Un resumen corto de 150-180 caracteres de la efeméride.
   - "content": El cuerpo principal de la efeméride, limpio, en español y bien redactado (propio de una revista de rock), separado por párrafos con salto de línea doble.
3. Evalúa la importancia ("importance"): un número del 1 al 5.
4. Omite saludos, despedidas o cualquier contenido irrelevante.

Devuelve la respuesta estrictamente en formato JSON utilizando el esquema indicado, el cual debe ser un arreglo ("array") de objetos.

Asunto del correo: {$subject}
Cuerpo del correo:
{$body}
PROMPT;
    }
}
