<?php

namespace Monek\Checkout\Domain\Checkout;

class CheckoutRequest
{
    private string $gatewayId;
    private string $mode;
    private string $token;
    private string $sessionIdentifier;
    private string $expiry;
    private string $paymentReference;

    public function __construct(
        string $gatewayId,
        string $mode,
        string $token,
        string $sessionIdentifier,
        string $expiry,
        string $paymentReference
    ) {
        $this->gatewayId = $gatewayId;
        $this->mode = $mode;
        $this->token = $token;
        $this->sessionIdentifier = $sessionIdentifier;
        $this->expiry = $expiry;
        $this->paymentReference = $paymentReference;
    }

    public function isForGateway(string $gatewayId): bool
    {
        if ($this->gatewayId === $gatewayId) {
            return true;
        }

        if ($this->gatewayId === $gatewayId . '-express') {
            return true;
        }

        return false;
    }

    public function isExpress(): bool
    {
        if ($this->mode === 'express') {
            return true;
        }

        if (substr($this->gatewayId, -8) === '-express') {
            return true;
        }

        return false;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getSessionIdentifier(): string
    {
        return $this->sessionIdentifier;
    }

    public function getExpiry(): string
    {
        return $this->expiry;
    }

    public function getPaymentReference(): string
    {
        return $this->paymentReference;
    }

    public function getGatewayId(): string
    {
        return $this->gatewayId;
    }
}
