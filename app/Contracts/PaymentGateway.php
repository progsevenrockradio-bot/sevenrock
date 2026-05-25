<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Talent;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * @return array{url:string,payment_id?:string,provider?:string}
     */
    public function createCheckoutSession(Talent $talent, string $plan): array;

    public function handleWebhook(Request $request): void;

    public function cancelSubscription(string $subscriptionId): bool;

    public function getName(): string;
}
