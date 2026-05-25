<?php

declare(strict_types=1);

namespace App\Support;

class TalentPlan
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'free' => [
                'label' => 'Free',
                'price' => 0.00,
                'currency' => 'EUR',
                'monthly_label' => '0€',
                'summary' => 'Perfil básico, 1 foto y 1 canción.',
                'limits' => [
                    'photos' => 1,
                    'songs' => 1,
                    'documents' => 0,
                    'videos' => 0,
                    'storage_mb' => 50,
                ],
                'features' => ['Perfil básico', '1 foto', '1 canción'],
            ],
            'basic' => [
                'label' => 'Basic',
                'price' => 3.50,
                'currency' => 'EUR',
                'monthly_label' => '3,5€',
                'summary' => '5 canciones, 10 fotos y estadísticas básicas.',
                'limits' => [
                    'photos' => 10,
                    'songs' => 5,
                    'documents' => 0,
                    'videos' => 0,
                    'storage_mb' => 200,
                ],
                'features' => ['5 canciones', '10 fotos', 'Estadísticas básicas'],
            ],
            'pro' => [
                'label' => 'Pro',
                'price' => 7.00,
                'currency' => 'EUR',
                'monthly_label' => '7€',
                'summary' => 'Documentos, videos y estadísticas detalladas.',
                'limits' => [
                    'photos' => 50,
                    'songs' => 20,
                    'documents' => 10,
                    'videos' => 5,
                    'storage_mb' => 1000,
                ],
                'features' => ['Documentos', 'Videos', 'Estadísticas detalladas'],
            ],
            'premium' => [
                'label' => 'Premium',
                'price' => 11.00,
                'currency' => 'EUR',
                'monthly_label' => '11€',
                'summary' => 'Historia destacada automática y prioridad en homepage.',
                'limits' => [
                    'photos' => 999,
                    'songs' => 999,
                    'documents' => 999,
                    'videos' => 999,
                    'storage_mb' => 5000,
                ],
                'features' => ['Historia destacada automática', 'Prioridad en homepage', 'Mayor capacidad'],
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(string $plan): array
    {
        return self::definitions()[$plan] ?? self::definitions()['free'];
    }

    public static function amount(string $plan): float
    {
        return (float) (self::definition($plan)['price'] ?? 0);
    }
}
