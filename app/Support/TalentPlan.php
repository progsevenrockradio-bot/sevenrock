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
                    'photo' => 1,
                    'mp3' => 1,
                    'document' => 0,
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
                    'photo' => 10,
                    'mp3' => 5,
                    'document' => 0,
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
                    'photo' => 25,
                    'mp3' => 15,
                    'document' => 20,
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
                    'photo' => 50,
                    'mp3' => 30,
                    'document' => 40,
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
