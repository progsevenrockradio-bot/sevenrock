<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ContentParserInterface;
use App\Support\AiPrompts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiContentParser implements ContentParserInterface
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
        $prompt = AiPrompts::getParsePrompt($subject, $body);

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
                                'enum' => ['post', 'release', 'event', 'discard'],
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
                            'events' => [
                                'type' => 'ARRAY',
                                'description' => 'Lista de conciertos, giras o eventos mencionados (solo para tipo event).',
                                'items' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'title' => ['type' => 'STRING', 'description' => 'El título del evento o ciudad de la gira.'],
                                        'starts_at' => ['type' => 'STRING', 'description' => 'Fecha de inicio del evento en formato YYYY-MM-DD o YYYY-MM-DD HH:mm:ss'],
                                        'location' => ['type' => 'STRING', 'description' => 'Ciudad y País/Estado del evento.'],
                                        'venue' => ['type' => 'STRING', 'description' => 'Lugar, sala o recinto del concierto.'],
                                        'ticket_url' => ['type' => 'STRING', 'description' => 'Enlace para comprar tickets.']
                                    ]
                                ]
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
                $status = $response->status();
                if ($status === 402) {
                    $this->lastError = "payment_required: " . $response->body();
                    return null; // Abort discovery on 402
                }
                
                $errorMsg = "HTTP Code " . $status . " - " . $response->body();
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
        $prompt = AiPrompts::getContactPrompt($subject, $body);

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
                $status = $response->status();
                if ($status === 402) {
                    $this->lastError = "payment_required: " . $response->body();
                    return null;
                }
                $errorMsg = "HTTP Code " . $status . " - " . $response->body();
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

    /**
     * Analiza el asunto y el cuerpo del correo para extraer una lista de efemérides usando Gemini.
     */
    public function parseEfemeridesBatch(string $subject, string $body, string $apiKey): ?array
    {
        $this->lastError = null;
        $prompt = AiPrompts::getEfemeridesPrompt($subject, $body);

        $model = config('services.gemini.model', 'gemini-flash-latest');

        // Primer intento
        $result = $this->callApiForBatch($model, $prompt, $apiKey);

        // Auto-descubrimiento en caso de error
        if ($result === null && ($this->isNotFoundError($this->lastError) || $this->isQuotaZeroError($this->lastError))) {
            $discoveredModel = $this->discoverBestModel($apiKey);
            if ($discoveredModel && $discoveredModel !== $model) {
                $this->lastError = null;
                $result = $this->callApiForBatch($discoveredModel, $prompt, $apiKey);
            }
        }

        return $result;
    }

    /**
     * Hace la llamada HTTP a la API de Gemini usando el esquema de Array (Batch).
     */
    protected function callApiForBatch(string $model, string $prompt, string $apiKey): ?array
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
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'title' => [
                                    'type' => 'STRING',
                                    'description' => 'El título de la efeméride.'
                                ],
                                'importance' => [
                                    'type' => 'INTEGER',
                                    'description' => 'Un valor del 1 al 5.'
                                ],
                                'excerpt' => [
                                    'type' => 'STRING',
                                    'description' => 'Resumen de 150-180 caracteres.'
                                ],
                                'content' => [
                                    'type' => 'STRING',
                                    'description' => 'El cuerpo principal de la efeméride.'
                                ]
                            ],
                            'required' => ['title', 'importance', 'excerpt', 'content']
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 402) {
                    $this->lastError = "payment_required: " . $response->body();
                    return null;
                }
                $errorMsg = "HTTP Code " . $status . " - " . $response->body();
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
