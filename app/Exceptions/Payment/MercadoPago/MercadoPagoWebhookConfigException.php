<?php

declare(strict_types=1);

namespace App\Exceptions\Payment\MercadoPago;

use App\Exceptions\Payment\PaymentWebhookAuthenticationException;

class MercadoPagoWebhookConfigException extends PaymentWebhookAuthenticationException
{
    public function __construct(string $message = 'MercadoPago webhook secret is not configured')
    {
        parent::__construct($message);
    }
}
