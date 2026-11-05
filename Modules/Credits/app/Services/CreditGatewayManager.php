<?php

declare(strict_types=1);

namespace Modules\Credits\app\Services;

use InvalidArgumentException;
use Modules\Credits\app\Contracts\CreditPaymentGatewayInterface;
use Modules\Credits\app\Gateways\FlutterwaveCreditGateway;
use Modules\Credits\app\Gateways\PaystackCreditGateway;

class CreditGatewayManager
{
    /**
     * @var array<string, CreditPaymentGatewayInterface>
     */
    protected array $drivers = [];

    public function __construct()
    {
        // Register default known gateway drivers
        $this->registerDriver(new PaystackCreditGateway);
        $this->registerDriver(new FlutterwaveCreditGateway);
    }

    public function registerDriver(CreditPaymentGatewayInterface $gateway): void
    {
        $this->drivers[strtolower($gateway->getIdentifier())] = $gateway;
    }

    /**
     * Get the default configured gateway name.
     */
    public function getDefaultDriverName(): string
    {
        return strtolower((string) (config('credits.default_gateway') ?: env('CREDITS_DEFAULT_GATEWAY', 'paystack')));
    }

    /**
     * Resolve a gateway instance by name or default to configured active gateway.
     */
    public function driver(?string $name = null): CreditPaymentGatewayInterface
    {
        $name = strtolower($name ?: $this->getDefaultDriverName());

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Unsupported credit payment gateway driver: [{$name}].");
        }

        return $this->drivers[$name];
    }

    /**
     * Get list of all registered gateway names.
     *
     * @return string[]
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->drivers);
    }
}
