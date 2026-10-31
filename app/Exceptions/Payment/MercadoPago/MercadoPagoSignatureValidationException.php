<?php

declare(strict_types=1);

namespace App\Exceptions\Payment\MercadoPago;

use App\Exceptions\Payment\PaymentWebhookAuthenticationException;

class MercadoPagoSignatureValidationException extends PaymentWebhookAuthenticationException
{
    public function __construct(
        private readonly string $failureReason,
        private readonly ?string $requestId = null,
        private readonly ?string $timestamp = null,
        string $message = 'MercadoPago webhook signature verification failed'
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

    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }
}
