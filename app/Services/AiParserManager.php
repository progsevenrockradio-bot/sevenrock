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

    /**
     * @return bool
     */
    public function hasAnyProvider(): bool
    {
        $chain = config('services.ai.provider_chain', ['gemini', 'openrouter']);
        
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
        $chain = config('services.ai.provider_chain', ['gemini', 'openrouter']);
        
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
