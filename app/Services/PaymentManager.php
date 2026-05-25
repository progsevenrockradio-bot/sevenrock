<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Services\Gateways\MercadoPagoGateway;
use App\Services\Gateways\PayPalGateway;
use App\Services\Gateways\StripeGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PaymentManager
{
    /**
     * @var array<string, PaymentGateway>
     */
    protected array $gateways = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function register(string $name, PaymentGateway $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    public function driver(?string $name = null): PaymentGateway
    {
        $name = $name ?: (string) config('payment.default', 'stripe');

        if (isset($this->gateways[$name])) {
            return $this->gateways[$name];
        }

        $gateway = match ($name) {
            'stripe' => $this->container->make(StripeGateway::class),
            'paypal' => $this->container->make(PayPalGateway::class),
            'mercadopago' => $this->container->make(MercadoPagoGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$name}]."),
        };

        $this->register($name, $gateway);

        return $gateway;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function plans(): array
    {
        return (array) config('payment.plans', []);
    }

    /**
     * @return array<string, PaymentGateway>
     */
    public function registered(): array
    {
        return $this->gateways;
    }

    /**
     * @return array<int, string>
     */
    public function getAvailable(): array
    {
        return array_keys($this->gateways);
    }
}
