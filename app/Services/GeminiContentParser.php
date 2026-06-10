<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiContentParser
{
    /**
     * Almacena el último error ocurrido durante la consulta a la API de Gemini.
     *
     * @var string|null
     */
    public ?string $lastError = null;

    /**
     * Process email content using Google Gemini API to clean, translate, and structure it.
     *
     * @param string $subject
     * @param string $body
     * @param string $apiKey
     * @return array|null
     */
    public function parse(string $subject, string $body, string $apiKey): ?array
    {
        $this->lastError = null;
        $prompt = <<<PROMPT
Eres un asistente de redacción editorial para la radio de rock "Seven Rock Radio".
Tu tarea es analizar el correo electrónico recibido (asunto y cuerpo) y convertirlo en contenido estructurado para la web.

El correo puede tratar sobre un "Nuevo Lanzamiento" de un disco/sencillo/video de una banda de rock, o bien ser una noticia general/artículo para el "Blog" (Post).

Sigue estas reglas estrictas:
1. Identifica el tipo de contenido:
   - "release": Si habla de un nuevo disco, EP, single, videoclip o canción recién lanzada por una banda/artista.
   - "post": Si es una noticia de música, crónica de concierto, artículo de opinión o texto informativo general.
2. Limpia el texto de firmas de correo, saludos iniciales (ej. "Hola Seven Rock Radio"), despedidas e información de contacto del email.
3. Traduce o reescribe el contenido al español con un tono periodístico, profesional, emocionante y con alta calidad gramatical (propio de una revista de rock).
4. Si es "release", identifica y separa el "artist_name" (Nombre de la banda/artista) y el "title" (Nombre de la canción o disco).
5. Si es "post", identifica y separa el "title" (un titular atractivo en español para el post).
6. Extrae los siguientes enlaces si se encuentran en el texto (deben ser URLs completas válidas):
   - "youtube_url": Enlace a un video de YouTube.
   - "spotify_url": Enlace a Spotify.
   - "facebook_url": Enlace a una página o publicación de Facebook.
   - "instagram_url": Enlace a Instagram.
   - "twitter_url": Enlace a Twitter/X.
7. Genera un "excerpt" (resumen corto de 150-180 caracteres) y el "content" (el cuerpo principal limpio y bien redactado, separado por párrafos con salto de línea doble).

Devuelve la respuesta estrictamente en formato JSON utilizando el esquema indicado.

Asunto del correo: {$subject}
Cuerpo del correo:
{$body}
PROMPT;

        $model = config('services.gemini.model', 'gemini-1.5-flash');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generation_config' => [
                    'response_mime_type' => 'application/json',
                    'response_schema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'type' => [
                                'type' => 'STRING',
                                'enum' => ['post', 'release'],
                                'description' => 'El tipo de contenido clasificado.'
                            ],
                            'title' => [
                                'type' => 'STRING',
                                'description' => 'El título del post o el título de la canción/disco lanzado.'
                            ],
                            'artist_name' => [
                                'type' => 'STRING',
                                'description' => 'El nombre del artista o banda (obligatorio para release, vacío para post).'
                            ],
                            'excerpt' => [
                                'type' => 'STRING',
                                'description' => 'Resumen o extracto de 150-180 caracteres.'
                            ],
                            'content' => [
                                'type' => 'STRING',
                                'description' => 'El cuerpo principal del artículo o descripción redactado en español.'
                            ],
                            'youtube_url' => [
                                'type' => 'STRING',
                                'description' => 'URL de YouTube extraída.'
                            ],
                            'spotify_url' => [
                                'type' => 'STRING',
                                'description' => 'URL de Spotify extraída.'
                            ],
                            'facebook_url' => [
                                'type' => 'STRING',
                                'description' => 'URL de Facebook extraída.'
                            ],
                            'instagram_url' => [
                                'type' => 'STRING',
                                'description' => 'URL de Instagram extraída.'
                            ],
                            'twitter_url' => [
                                'type' => 'STRING',
                                'description' => 'URL de Twitter/X extraída.'
                            ]
                        ],
                        'required' => ['type', 'title', 'excerpt', 'content']
                    ]
                ]
            ]);

            if ($response->failed()) {
                $this->lastError = "HTTP Code " . $response->status() . " - " . $response->body();
                Log::error("Gemini API Error: " . $response->body());
                return null;
            }

            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! $text) {
                $this->lastError = "Response does not contain text. Response candidate block: " . json_encode($result);
                Log::error("Gemini API returned empty text candidate.");
                return null;
            }

            $parsedData = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->lastError = "JSON decode error: " . json_last_error_msg() . ". Raw text: " . $text;
                Log::error("Failed to decode Gemini JSON response: " . json_last_error_msg());
                return null;
            }

            return $parsedData;

        } catch (\Throwable $e) {
            $this->lastError = "Exception in GeminiContentParser: " . $e->getMessage();
            Log::error("Exception in GeminiContentParser: " . $e->getMessage());
            return null;
        }
    }
}
