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
                'label' => 'Gratuito',
                'price' => 0.00,
                'currency' => 'EUR',
                'monthly_label' => '0€',
                'summary' => 'Perfil básico, hasta 2 canciones MP3 (máx. 12 MB c/u), 1 fotografía (máx. 10 MB) y 50 MB de almacenamiento total.',
                'limits' => [
                    'photos' => 1,
                    'songs' => 2,
                    'documents' => 0,
                    'videos' => 0,
                    'storage_mb' => 50,
                ],
                'max_file_size_mb' => [
                    'songs' => 12,
                    'photos' => 10,
                    'documents' => 0,
                    'videos' => 0,
                ],
                'features' => [
                    'Perfil básico',
                    'Hasta 2 canciones MP3 (máx. 12 MB c/u)',
                    '1 fotografía (máx. 10 MB)',
                    '50 MB de almacenamiento total',
                ],
            ],
            'basic' => [
                'label' => 'Básico',
                'price' => 3.50,
                'currency' => 'EUR',
                'monthly_label' => '3,50 €/mes',
                'paypal_url' => 'https://www.paypal.com/ncp/payment/FTNT3HTH6KXR2',
                'summary' => 'Perfil público con biografía y redes oficiales, hasta 5 canciones (máx. 15 MB c/u), 10 fotografías (máx. 10 MB c/u) y 200 MB de almacenamiento total. Enlaces a YouTube incluidos (sin subida de archivos de vídeo).',
                'limits' => [
                    'photos' => 10,
                    'songs' => 5,
                    'documents' => 0,
                    'videos' => 0,
                    'storage_mb' => 200,
                ],
                'max_file_size_mb' => [
                    'songs' => 15,
                    'photos' => 10,
                    'documents' => 0,
                    'videos' => 0,
                ],
                'features' => [
                    'Perfil público con biografía y redes oficiales',
                    'Hasta 5 canciones MP3 (máx. 15 MB c/u)',
                    'Hasta 10 fotografías (máx. 10 MB c/u)',
                    '200 MB de almacenamiento total',
                    'Enlaces a YouTube (sin subida de archivos de vídeo)',
                ],
            ],
            'pro' => [
                'label' => 'Pro',
                'price' => 7.00,
                'currency' => 'EUR',
                'monthly_label' => '7 €/mes',
                'paypal_url' => 'https://www.paypal.com/ncp/payment/SHQVV9LS29V8U',
                'summary' => 'Perfil público con biografía, redes y enlaces a YouTube, hasta 15 canciones MP3 (máx. 20 MB c/u), 50 fotografías (máx. 10 MB c/u), 10 documentos PDF (máx. 10 MB c/u), 3 vídeos MP4/WebM (máx. 50 MB c/u) y 400 MB de almacenamiento total compartido.',
                'limits' => [
                    'photos' => 50,
                    'songs' => 15,
                    'documents' => 10,
                    'videos' => 3,
                    'storage_mb' => 400,
                ],
                'max_file_size_mb' => [
                    'songs' => 20,
                    'photos' => 10,
                    'documents' => 10,
                    'videos' => 50,
                ],
                'features' => [
                    'Perfil público con biografía, redes y enlaces a YouTube',
                    'Hasta 15 canciones MP3 (máx. 20 MB c/u)',
                    'Hasta 50 fotografías (máx. 10 MB c/u)',
                    'Hasta 10 documentos PDF (máx. 10 MB c/u)',
                    'Hasta 3 vídeos MP4/WebM (máx. 50 MB c/u)',
                    '400 MB de almacenamiento total compartido',
                ],
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

    public static function paypalUrl(string $plan): ?string
    {
        return config("payment.paypal_links.$plan") ?? self::definition($plan)['paypal_url'] ?? null;
    }
}
