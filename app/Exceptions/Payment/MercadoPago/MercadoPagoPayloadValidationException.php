<?php

declare(strict_types=1);

namespace App\Exceptions\Payment\MercadoPago;

use App\Exceptions\Payment\PaymentWebhookAuthenticationException;

class MercadoPagoPayloadValidationException extends PaymentWebhookAuthenticationException
{
    public function __construct(
        private readonly string $failureReason,
        private readonly ?string $requestId = null,
        string $message = 'MercadoPago webhook payload or identifier validation failed'
    ) {
        parent::__construct($message);
    }

    public function getFailureReason(): string
    {
        return $this->failureReason;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
