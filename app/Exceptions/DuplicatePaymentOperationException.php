<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TalentSubscription;
use RuntimeException;

class DuplicatePaymentOperationException extends RuntimeException
{
    public function __construct(
        public readonly string $operationId,
        public readonly ?TalentSubscription $existingSubscription = null,
        ?string $customMessage = null
    ) {
        $msg = $customMessage;
        if (! $msg && $existingSubscription) {
            $band = $existingSubscription->talent?->band_name ?? 'otra banda';
            $date = $existingSubscription->confirmed_at?->format('d/m/Y H:i') ?? ($existingSubscription->created_at?->format('d/m/Y H:i') ?? 'fecha anterior');
            $admin = $existingSubscription->confirmedBy?->name ?? 'Admin';
            $msg = "La operación '{$operationId}' ya fue registrada previamente para la banda '{$band}' el {$date} por {$admin}. No es posible reutilizar una misma operación de pago.";
        }

        parent::__construct($msg ?? "La operación de pago '{$operationId}' ya ha sido utilizada previamente.");
    }
}

