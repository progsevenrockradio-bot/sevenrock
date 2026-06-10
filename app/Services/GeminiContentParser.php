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

        $model = config('services.gemini.model', 'gemini-flash-latest');

        // Primer intento con el modelo configurado
        $result = $this->callApi($model, $prompt, $apiKey);

        // Si falló por modelo no encontrado (404) o por cuota excedida con límite 0 (429 con limit: 0)
        if ($result === null && ($this->isNotFoundError($this->lastError) || $this->isQuotaZeroError($this->lastError))) {
            Log::warning("GeminiContentParser: El modelo '{$model}' falló (404/429). Iniciando auto-descubrimiento de modelos alternativos...");
            
            $discoveredModel = $this->discoverBestModel($apiKey);
            if ($discoveredModel && $discoveredModel !== $model) {
                Log::info("GeminiContentParser: Modelo alternativo descubierto: '{$discoveredModel}'. Reintentando análisis...");
                $this->lastError = null;
                $result = $this->callApi($discoveredModel, $prompt, $apiKey);
            }
        }

        return $result;
    }

    /**
     * Hace la llamada HTTP a la API de Gemini.
     */
    protected function callApi(string $model, string $prompt, string $apiKey): ?array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'type' => [
                                'type' => 'STRING',
                                'enum' => ['post', 'release', 'discard'],
                                'description' => 'El tipo de contenido clasificado.'
                            ],
                            'importance' => [
                                'type' => 'INTEGER',
                                'description' => 'Un valor del 1 al 5 que califica la importancia o relevancia.'
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
                        'required' => ['type', 'importance', 'title', 'excerpt', 'content']
                    ]
                ]
            ]);

            if ($response->failed()) {
                $errorMsg = "HTTP Code " . $response->status() . " - " . $response->body();
                $this->lastError = $errorMsg;
                Log::error("Gemini API Error for model {$model}: " . $response->body());
                return null;
            }

            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! $text) {
                $this->lastError = "Response does not contain text. Response candidate block: " . json_encode($result);
                Log::error("Gemini API returned empty text candidate for model {$model}.");
                return null;
            }

            $parsedData = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->lastError = "JSON decode error: " . json_last_error_msg() . ". Raw text: " . $text;
                Log::error("Failed to decode Gemini JSON response for model {$model}: " . json_last_error_msg());
                return null;
            }

            return $parsedData;

        } catch (\Throwable $e) {
            $this->lastError = "Exception for model {$model}: " . $e->getMessage();
            Log::error("Exception in GeminiContentParser for model {$model}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Descubre el mejor modelo disponible para la API Key.
     */
    protected function discoverBestModel(string $apiKey): ?string
    {
        try {
            $response = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");
            if ($response->successful()) {
                $modelsData = $response->json();
                $availableModels = [];
                foreach ($modelsData['models'] ?? [] as $m) {
                    if (isset($m['name'])) {
                        $name = str_replace('models/', '', $m['name']);
                        // Solo modelos que soporten generación de contenido
                        if (isset($m['supportedGenerationMethods']) && in_array('generateContent', $m['supportedGenerationMethods'])) {
                            $availableModels[] = $name;
                        }
                    }
                }

                // Lista de modelos preferidos en orden de prioridad (modelos Flash estables y económicos)
                $preferredModels = [
                    'gemini-flash-latest',
                    'gemini-1.5-flash',
                    'gemini-2.5-flash',
                    'gemini-2.0-flash-lite',
                    'gemini-3.1-flash-lite',
                    'gemini-1.5-flash-latest',
                ];

                // 1. Buscar coincidencia exacta en nuestra lista de preferidos
                foreach ($preferredModels as $pref) {
                    if (in_array($pref, $availableModels, true)) {
                        return $pref;
                    }
                }

                // 2. Si no hay coincidencia exacta de los preferidos, buscar cualquier modelo que contenga "flash"
                foreach ($availableModels as $modelName) {
                    if (str_contains(strtolower($modelName), 'flash')) {
                        return $modelName;
                    }
                }

                // 3. Si no hay ninguno con "flash", tomar el primero disponible
                if (!empty($availableModels)) {
                    return $availableModels[0];
                }
            }
        } catch (\Throwable $e) {
            Log::error("GeminiContentParser: Falló el auto-descubrimiento de modelos: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Verifica si el error es de tipo "No Encontrado" (404).
     */
    protected function isNotFoundError(?string $error): bool
    {
        if (!$error) {
            return false;
        }
        return str_contains($error, '404') || str_contains(strtoupper($error), 'NOT_FOUND');
    }

    /**
     * Verifica si el error es de tipo "Cuota Excedida / Límite de 0" (429 con limit: 0).
     */
    protected function isQuotaZeroError(?string $error): bool
    {
        if (!$error) {
            return false;
        }
        $lowerError = strtolower($error);
        return str_contains($lowerError, '429') && (str_contains($lowerError, 'limit: 0') || str_contains($lowerError, 'resource_exhausted'));
    }

    /**
     * Analiza el asunto y el cuerpo del correo para extraer información del remitente usando Gemini.
     */
    public function parseContactInfo(string $subject, string $body, string $apiKey): ?array
    {
        $this->lastError = null;
        $prompt = <<<PROMPT
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

        $model = config('services.gemini.model', 'gemini-flash-latest');

        // Primer intento
        $result = $this->callApiForContact($model, $prompt, $apiKey);

        // Auto-descubrimiento en caso de error
        if ($result === null && ($this->isNotFoundError($this->lastError) || $this->isQuotaZeroError($this->lastError))) {
            $discoveredModel = $this->discoverBestModel($apiKey);
            if ($discoveredModel && $discoveredModel !== $model) {
                $this->lastError = null;
                $result = $this->callApiForContact($discoveredModel, $prompt, $apiKey);
            }
        }

        return $result;
    }

    /**
     * Hace la llamada HTTP a la API de Gemini usando el esquema de Contactos.
     */
    protected function callApiForContact(string $model, string $prompt, string $apiKey): ?array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'name' => [
                                'type' => 'STRING',
                                'description' => 'Nombre completo o de pila de la persona de contacto.'
                            ],
                            'company_or_band' => [
                                'type' => 'STRING',
                                'description' => 'Nombre de la empresa de relaciones públicas, disquera, agencia o banda de rock.'
                            ],
                            'role' => [
                                'type' => 'STRING',
                                'description' => 'Cargo, puesto o descripción del rol en el correo.'
                            ]
                        ],
                        'required' => ['name', 'company_or_band', 'role']
                    ]
                ]
            ]);

            if ($response->failed()) {
                $errorMsg = "HTTP Code " . $response->status() . " - " . $response->body();
                $this->lastError = $errorMsg;
                return null;
            }

            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! $text) {
                $this->lastError = "Response does not contain text.";
                return null;
            }

            $parsedData = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->lastError = "JSON decode error: " . json_last_error_msg();
                return null;
            }

            return $parsedData;

        } catch (\Throwable $e) {
            $this->lastError = "Exception: " . $e->getMessage();
            return null;
        }
    }
}
