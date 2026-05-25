<?php

use App\Support\TalentPlan;

return [
    'default' => env('PAYMENT_DEFAULT', 'stripe'),

    'plans' => collect(TalentPlan::definitions())
        ->map(fn (array $plan, string $key): array => [
            'key' => $key,
            'label' => $plan['label'],
            'amount' => (float) $plan['price'],
            'price' => (float) $plan['price'],
            'currency' => (string) ($plan['currency'] ?? 'EUR'),
            'monthly_label' => (string) ($plan['monthly_label'] ?? ''),
            'summary' => (string) ($plan['summary'] ?? ''),
            'features' => (array) ($plan['features'] ?? []),
            'limits' => (array) ($plan['limits'] ?? []),
        ])
        ->all(),

    'gateways' => [
        'stripe' => ['label' => 'Stripe'],
        'paypal' => ['label' => 'PayPal'],
        'mercadopago' => ['label' => 'MercadoPago'],
    ],
];
