<?php

use App\Support\TalentPlan;

return [
    'default' => env('PAYMENT_DEFAULT', 'stripe'),

    'paypal_links' => [
        'basic' => env('PAYPAL_LINK_BASIC', 'https://www.paypal.com/ncp/payment/FTNT3HTH6KXR2'),
        'pro' => env('PAYPAL_LINK_PRO', 'https://www.paypal.com/ncp/payment/SHQVV9LS29V8U'),
    ],

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
            'paypal_url' => $plan['paypal_url'] ?? config("payment.paypal_links.$key"),
        ])
        ->all(),

    'gateways' => [
        'stripe' => ['label' => 'Stripe'],
        'paypal' => ['label' => 'PayPal'],
        'mercadopago' => ['label' => 'MercadoPago'],
    ],
];
