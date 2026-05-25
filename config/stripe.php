<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'key' => env('STRIPE_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'prices' => [
        'free' => env('STRIPE_PRICE_FREE'),
        'basic' => env('STRIPE_PRICE_BASIC'),
        'pro' => env('STRIPE_PRICE_PRO'),
        'premium' => env('STRIPE_PRICE_PREMIUM'),
    ],
];
