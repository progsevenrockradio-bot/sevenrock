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
