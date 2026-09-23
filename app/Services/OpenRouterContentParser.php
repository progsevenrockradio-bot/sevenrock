<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ContentParserInterface;
use App\Support\AiPrompts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterContentParser implements ContentParserInterface
{
    /**
     * Almacena el último error ocurrido durante la consulta a la API.
     *
     * @var string|null
     */
    public ?string $lastError = null;

    public function parse(string $subject, string $body, string $apiKey): ?array
    {
        $this->lastError = null;
        $prompt = AiPrompts::getParsePrompt($subject, $body);

        return $this->callApi($prompt, $apiKey, [
            'type' => 'OBJECT',
            'properties' => [
                'type' => ['type' => 'STRING', 'enum' => ['post', 'release', 'event', 'discard']],
                'importance' => ['type' => 'INTEGER'],
                'title' => ['type' => 'STRING'],
                'artist_name' => ['type' => 'STRING'],
                'excerpt' => ['type' => 'STRING'],
                'content' => ['type' => 'STRING'],
                'events' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'title' => ['type' => 'STRING'],
                            'starts_at' => ['type' => 'STRING'],
                            'location' => ['type' => 'STRING'],
                            'venue' => ['type' => 'STRING'],
                            'ticket_url' => ['type' => 'STRING']
                        ]
                    ]
                ],
                'youtube_url' => ['type' => 'STRING'],
                'spotify_url' => ['type' => 'STRING'],
                'facebook_url' => ['type' => 'STRING'],
                'instagram_url' => ['type' => 'STRING'],
                'twitter_url' => ['type' => 'STRING']
            ],
            'required' => ['type', 'importance', 'title', 'excerpt', 'content']
        ]);
    }

    public function parseContactInfo(string $subject, string $body, string $apiKey): ?array
    {
        $this->lastError = null;
        $prompt = AiPrompts::getContactPrompt($subject, $body);

        return $this->callApi($prompt, $apiKey, [
            'type' => 'OBJECT',
            'properties' => [
                'name' => ['type' => 'STRING'],
                'company_or_band' => ['type' => 'STRING'],
                'role' => ['type' => 'STRING']
            ],
            'required' => ['name', 'company_or_band', 'role']
        ]);
    }

    public function parseEfemeridesBatch(string $subject, string $body, string $apiKey): ?array
    {
        $this->lastError = null;
        $prompt = AiPrompts::getEfemeridesPrompt($subject, $body);

        return $this->callApi($prompt, $apiKey, [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'title' => ['type' => 'STRING'],
                    'importance' => ['type' => 'INTEGER'],
                    'excerpt' => ['type' => 'STRING'],
                    'content' => ['type' => 'STRING']
                ],
                'required' => ['title', 'importance', 'excerpt', 'content']
            ]
        ]);
    }

    protected function callApi(string $prompt, string $apiKey, array $jsonSchema = null): ?array
    {
        $model = config('services.openrouter.model', 'google/gemini-2.5-flash');
        $baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'https://sevenrockradio.com',
                'X-Title' => 'Seven Rock Radio'
            ])->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.4
            ]);

            if ($response->failed()) {
                $status = $response->status();
                $errorMsg = "http_{$status}: " . $response->body();
                $this->lastError = $errorMsg;
                Log::error("OpenRouter API Error for model {$model}: " . $response->body());
                return null;
            }

            $result = $response->json();
            $text = $result['choices'][0]['message']['content'] ?? null;

            if (! $text) {
                $this->lastError = "Response does not contain text. Raw: " . json_encode($result);
                return null;
            }

            $parsedData = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->lastError = "JSON decode error: " . json_last_error_msg() . ". Raw text: " . $text;
                return null;
            }

            // Note: OpenRouter JSON object responses for Gemini sometimes wrap in the schema key.
            // Since we use response_format: type: json_object without strict structured outputs,
            // the LLM typically returns a raw JSON object.
            
            return $parsedData;

        } catch (\Throwable $e) {
            $this->lastError = "Exception for model {$model}: " . $e->getMessage();
            Log::error("Exception in OpenRouterContentParser: " . $e->getMessage());
            return null;
        }
    }
}
