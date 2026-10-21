<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCallAudit extends Model
{
    protected $table = 'ai_call_audits';

    protected $fillable = [
        'message_id',
        'content_hash',
        'provider',
        'model',
        'stage',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'usage_source',
        'estimated_cost_eur',
        'duration_ms',
        'status',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'prompt_tokens'      => 'integer',
        'completion_tokens'  => 'integer',
        'total_tokens'       => 'integer',
        'estimated_cost_eur' => 'float',
        'duration_ms'        => 'integer',
        'retry_count'        => 'integer',
    ];

    /**
     * Calcula el coste estimado en euros según el modelo y los tokens.
     */
    public static function calculateEstimatedCost(string $provider, string $model, int $promptTokens, int $completionTokens): float
    {
        $provider = strtolower($provider);
        $model = strtolower($model);

        // Tarifas base aproximadas por millón de tokens convertidas a EUR (1 USD ≈ 0.92 EUR)
        if ($provider === 'gemini') {
            if (str_contains($model, 'flash')) {
                // Gemini Flash: ~$0.075 / 1M input, ~$0.30 / 1M output
                $inputRate = (0.075 / 1_000_000) * 0.92;
                $outputRate = (0.30 / 1_000_000) * 0.92;
                return round(($promptTokens * $inputRate) + ($completionTokens * $outputRate), 6);
            }
            if (str_contains($model, 'pro')) {
                // Gemini Pro: ~$3.50 / 1M input, ~$10.50 / 1M output
                $inputRate = (3.50 / 1_000_000) * 0.92;
                $outputRate = (10.50 / 1_000_000) * 0.92;
                return round(($promptTokens * $inputRate) + ($completionTokens * $outputRate), 6);
            }
        }

        if ($provider === 'openrouter') {
            if (str_contains($model, 'free')) {
                return 0.000000;
            }
            // Promedio modelos eficientes OpenRouter: ~$0.20 / 1M tokens
            $rate = (0.20 / 1_000_000) * 0.92;
            return round(($promptTokens + $completionTokens) * $rate, 6);
        }

        // Coste predeterminado conservador: 0.005 € por llamada si no se especifica
        return 0.005000;
    }
}
