<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ContentParserInterface;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Log;

class AiParserManager
{
    public ?string $lastError = null;
    public ?string $lastProvider = null;
    public ?array $lastTelemetry = null;

    public function resolveChain(): array
    {
        $settings = ThemeSetting::current();
        $dbChainStr = $settings->ai_provider_chain ?? '';
        $configChain = config('services.ai.provider_chain', ['gemini', 'openrouter']);
        
        $chain = $dbChainStr ? explode(',', $dbChainStr) : $configChain;
        
        if ($chain !== $configChain) {
            Log::info("AiParserManager: Cadena efectiva (" . implode(',', $chain) . ") difiere de la config (" . implode(',', $configChain) . ")");
        }
        
        $fallbackEnabled = (bool) ($settings->ai_fallback_enabled ?? true);
        if (!$fallbackEnabled && count($chain) > 1) {
            $chain = [$chain[0]];
        }
        
        return $chain;
    }

    /**
     * @return bool
     */
    public function hasAnyProvider(): bool
    {
        $chain = $this->resolveChain();
        
        foreach ($chain as $provider) {
            $key = $this->getApiKey($provider);
            if (!empty($key)) {
                return true;
            }
        }
        
        return false;
    }

    public function parse(string $subject, string $body): ?array
    {
        return $this->executeChain('parse', [$subject, $body]);
    }

    public function parseContactInfo(string $subject, string $body): ?array
    {
        return $this->executeChain('parseContactInfo', [$subject, $body]);
    }

    public function parseEfemeridesBatch(string $subject, string $body): ?array
    {
        return $this->executeChain('parseEfemeridesBatch', [$subject, $body]);
    }

    /**
     * Ejecuta una llamada estructurada recorriendo la cadena de proveedores y auditando consumo.
     */
    public function generateStructured(
        string $prompt,
        array $schema,
        string $stage = 'generic',
        ?string $messageId = null,
        ?string $contentHash = null
    ): ?array {
        $this->lastError = null;
        $this->lastProvider = null;
        $this->lastTelemetry = null;

        $chain = $this->resolveChain();
        $errors = [];
        $contentHash = $contentHash ?: hash('sha256', $prompt);

        foreach ($chain as $provider) {
            $apiKey = $this->getApiKey($provider);
            if (empty($apiKey)) {
                $errors[] = "{$provider}: api_key_missing";
                continue;
            }

            $driver = $this->resolveDriver($provider);
            if (!$driver || !method_exists($driver, 'generateStructured')) {
                $errors[] = "{$provider}: driver_unsupported";
                continue;
            }

            // Registrar llamada en contador diario
            app(\App\Services\AiUsageTracker::class)->incrementCall($provider);

            $result = $driver->generateStructured($prompt, $schema, $apiKey);
            $telemetry = $driver->lastTelemetry ?? [];

            if ($result !== null) {
                $this->lastProvider = $provider;
                $this->lastTelemetry = $telemetry;

                // Registrar auditoría exitosa si la tabla existe
                $this->recordAudit([
                    'message_id'        => $messageId,
                    'content_hash'      => $contentHash,
                    'provider'          => $provider,
                    'model'             => $telemetry['model'] ?? 'unknown',
                    'stage'             => $stage,
                    'prompt_tokens'     => $telemetry['prompt_tokens'] ?? null,
                    'completion_tokens' => $telemetry['completion_tokens'] ?? null,
                    'total_tokens'      => $telemetry['total_tokens'] ?? null,
                    'usage_source'      => $telemetry['usage_source'] ?? 'estimated',
                    'estimated_cost_eur'=> \App\Models\AiCallAudit::calculateEstimatedCost(
                        $provider,
                        $telemetry['model'] ?? 'unknown',
                        (int) ($telemetry['prompt_tokens'] ?? 0),
                        (int) ($telemetry['completion_tokens'] ?? 0)
                    ),
                    'duration_ms'       => $telemetry['duration_ms'] ?? 0,
                    'status'            => 'success',
                ]);

                return $result;
            } else {
                $err = $driver->lastError ?? 'unknown_error';
                $errors[] = "{$provider}: {$err}";
                Log::warning("AiParserManager: Proveedor {$provider} falló en generateStructured.", ['error' => $err]);

                // Registrar auditoría de error
                $this->recordAudit([
                    'message_id'        => $messageId,
                    'content_hash'      => $contentHash,
                    'provider'          => $provider,
                    'model'             => method_exists($driver, 'resolveModel') ? $driver->resolveModel() : 'unknown',
                    'stage'             => $stage,
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'total_tokens'      => 0,
                    'usage_source'      => 'estimated',
                    'estimated_cost_eur'=> 0.0,
                    'duration_ms'       => 0,
                    'status'            => 'error',
                    'error_message'     => $err,
                ]);
            }
        }

        $this->lastError = implode(' | ', $errors);
        return null;
    }

    protected function recordAudit(array $data): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ai_call_audits')) {
                \App\Models\AiCallAudit::create($data);
            }
        } catch (\Throwable $e) {
            Log::error("AiParserManager: Error al registrar AiCallAudit: " . $e->getMessage());
        }
    }


    protected function executeChain(string $method, array $args): ?array
    {
        $this->lastError = null;
        $this->lastProvider = null;
        $chain = $this->resolveChain();
        
        $errors = [];

        foreach ($chain as $provider) {
            $apiKey = $this->getApiKey($provider);
            
            if (empty($apiKey)) {
                $errors[] = "{$provider}: api_key_missing";
                continue;
            }

            $parser = $this->resolveDriver($provider);
            
            if (!$parser) {
                $errors[] = "{$provider}: driver_not_found";
                continue;
            }

            // The parser methods take the API key as the last argument
            $callArgs = $args;
            $callArgs[] = $apiKey;

            // Incrementar contador de llamadas antes de realizar la petición HTTP
            app(\App\Services\AiUsageTracker::class)->incrementCall($provider);

            $result = call_user_func_array([$parser, $method], $callArgs);

            if ($result !== null) {
                $this->lastProvider = $provider;
                return $result;
            } else {
                $error = $parser->lastError ?? 'unknown_error';
                $errors[] = "{$provider}: {$error}";
                Log::warning("AiParserManager: Proveedor {$provider} falló.", ['error' => $error]);
            }
        }

        $this->lastError = implode(' | ', $errors);
        return null;
    }

    /**
     * Determina si un error retornado por la IA es recuperable con reintentos (429, 500, 503, timeouts).
     */
    public function isRecoverableError(?string $error): bool
    {
        if (empty($error)) {
            return false;
        }

        $errorLower = strtolower($error);

        // 429 Too Many Requests / Quota limit
        if (str_contains($errorLower, '429') || 
            str_contains($errorLower, 'too many requests') || 
            str_contains($errorLower, 'resource_exhausted')) {
            return true;
        }

        // Errores transitorios de servidor (500, 502, 503, 504)
        if (str_contains($errorLower, '500') || 
            str_contains($errorLower, '502') || 
            str_contains($errorLower, '503') || 
            str_contains($errorLower, '504') ||
            str_contains($errorLower, 'service unavailable') ||
            str_contains($errorLower, 'bad gateway')) {
            return true;
        }

        // Timeouts y caídas de red transitorias
        if (str_contains($errorLower, 'timeout') || 
            str_contains($errorLower, 'timed out') || 
            str_contains($errorLower, 'curl error 28') ||
            str_contains($errorLower, 'connection reset')) {
            return true;
        }

        return false;
    }

    protected function getApiKey(string $provider): ?string
    {
        $settings = ThemeSetting::current();
        
        if ($provider === 'gemini') {
            return ($settings->gemini_api_key ?? null) ?: config('services.gemini.api_key');
        }
        
        if ($provider === 'openrouter') {
            return ($settings->openrouter_api_key ?? null) ?: config('services.openrouter.api_key');
        }

        return null;
    }

    protected function resolveDriver(string $provider): ?ContentParserInterface
    {
        if ($provider === 'gemini') {
            return app(GeminiContentParser::class);
        }
        
        if ($provider === 'openrouter') {
            return app(OpenRouterContentParser::class);
        }

        return null;
    }
}
