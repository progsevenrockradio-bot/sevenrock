<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use RuntimeException;

abstract class PaymentWebhookAuthenticationException extends RuntimeException
{
}
