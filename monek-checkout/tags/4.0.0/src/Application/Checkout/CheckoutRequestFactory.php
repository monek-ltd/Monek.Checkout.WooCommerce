<?php

namespace Monek\Checkout\Application\Checkout;

use Automattic\WooCommerce\Blocks\Payments\PaymentContext;
use Monek\Checkout\Domain\Checkout\CheckoutRequest;

class CheckoutRequestFactory
{
    public function createFromPaymentContext(PaymentContext $context): CheckoutRequest
    {
        $paymentData = $this->sanitisePaymentData($context->payment_data ?? []);

        $gatewayId = $this->resolveGatewayIdentifier($context);
        $mode = isset($paymentData['monek_mode']) ? (string) $paymentData['monek_mode'] : '';
        $token = isset($paymentData['monek_token']) ? (string) $paymentData['monek_token'] : '';
        $sessionIdentifier = isset($paymentData['monek_session']) ? (string) $paymentData['monek_session'] : '';
        $expiry = isset($paymentData['monek_expiry']) ? (string) $paymentData['monek_expiry'] : '';
        $paymentReference = isset($paymentData['monek_reference']) ? (string) $paymentData['monek_reference'] : '';

        return new CheckoutRequest(
            $gatewayId,
            $mode,
            $token,
            $sessionIdentifier,
            $expiry,
            $paymentReference
        );
    }

    private function sanitisePaymentData($rawPaymentData): array
    {
        if (! is_array($rawPaymentData)) {
            return [];
        }

        $sanitised = [];
        foreach ($rawPaymentData as $key => $value) {
            $resolvedKey = $this->resolveEntryKey($key, $value);
            if ($resolvedKey === '') {
                continue;
            }

            $sanitised[$resolvedKey] = $this->resolveEntryValue($value);
        }

        return $sanitised;
    }

    private function resolveEntryKey($key, $value): string
    {
        if (is_array($value) && array_key_exists('key', $value)) {
            return sanitize_text_field((string) $value['key']);
        }

        if (is_object($value) && isset($value->key)) {
            return sanitize_text_field((string) $value->key);
        }

        return sanitize_text_field((string) $key);
    }

    private function resolveGatewayIdentifier(PaymentContext $context): string
    {
        $candidate = '';

        if (!isset($context->payment_method)) {
            $candidate = $context->payment_method;
        }

        return $this->cleanTextValue($candidate);
    }

    private function resolveEntryValue($value)
    {
        if (is_array($value) && array_key_exists('value', $value)) {
            return $this->cleanValue($value['value']);
        }

        if (is_object($value) && isset($value->value)) {
            return $this->cleanValue($value->value);
        }

        return $this->cleanValue($value);
    }

    private function cleanValue($value)
    {
        if (is_string($value)) {
            return wc_clean(wp_unslash($value));
        }

        if (is_scalar($value)) {
            return $value;
        }

        return '';
    }

    private function cleanTextValue($value): string
    {
        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_scalar($value)) {
            return sanitize_text_field((string) $value);
        }

        return '';
    }
}
