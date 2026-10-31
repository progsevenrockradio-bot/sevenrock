<?php

declare(strict_types=1);

namespace App\Services\Gateways\MercadoPago;

use Illuminate\Http\Request;

interface MercadoPagoWebhookAuthenticatorInterface
{
    /**
     * Authenticates an incoming MercadoPago webhook request using HMAC-SHA256 signature verification.
     *
     * @param Request $request
     * @return string The validated, non-empty, authoritative signed resource identifier (data.id).
     *
     * @throws \App\Exceptions\Payment\MercadoPago\MercadoPagoWebhookConfigException
     * @throws \App\Exceptions\Payment\MercadoPago\MercadoPagoSignatureValidationException
     * @throws \App\Exceptions\Payment\MercadoPago\MercadoPagoPayloadValidationException
     */
    public function authenticate(Request $request): string;
}
