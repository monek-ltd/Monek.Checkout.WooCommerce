<?php

namespace Monek\Checkout\Application\Checkout;

use Monek\Checkout\Infrastructure\Logging\Logger;
use WC_Order;

class PaymentProcessor
{
    private string $publishableKey;
    private string $secretKey;
    private PaymentPayloadBuilder $payloadBuilder;
    private Logger $logger;

    public function __construct(
        string $publishableKey,
        string $secretKey,
        PaymentPayloadBuilder $payloadBuilder,
        Logger $logger
    ) {
        $this->publishableKey = $publishableKey;
        $this->secretKey = $secretKey;
        $this->payloadBuilder = $payloadBuilder;
        $this->logger = $logger;
    }

    /**
     * @return array{success:bool,message:?string,auth_code:?string,error_code:?string,raw:?array}
     */
    public function process(
        WC_Order $order,
        string $token,
        string $sessionIdentifier,
        string $expiry,
        string $paymentReference
    ): array {
        $endpoint = 'https://api.monek.com/embedded-checkout/payment';

        $payload = $this->payloadBuilder->build(
            $order,
            $token,
            $sessionIdentifier,
            $expiry,
            $paymentReference
        );

        $requestArguments = [
            'method' => 'POST',
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Api-Key' => $this->publishableKey,
                'X-Secret-Key' => $this->secretKey,
            ],
            'body' => wp_json_encode($payload),
            'data_format' => 'body',
        ];

        $response = wp_remote_post($endpoint, $requestArguments);

        if (is_wp_error($response)) {
            $this->logger->error('Payment request failed to send', ['error' => $response->get_error_message()]);

            return [
                'success' => false,
                'message' => 'Payment request failed to send',
                'auth_code' => null,
                'error_code' => $response->get_error_code(),
                'raw' => null,
            ];
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);
        $decodedBody = json_decode($rawBody, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->error('Payment request returned error', [
                'status' => $statusCode,
                'body' => $decodedBody,
            ]);

            return [
                'success' => false,
                'message' => sprintf('payment failed (%d)', $statusCode),
                'auth_code' => null,
                'error_code' => isset($decodedBody['ErrorCode']) ? (string) $decodedBody['ErrorCode'] : null,
                'raw' => is_array($decodedBody) ? $decodedBody : null,
            ];
        }

        $result = $decodedBody['Result'] ?? $decodedBody['result'] ?? null;
        $message = $decodedBody['Message'] ?? $decodedBody['message'] ?? null;
        $authCode = $decodedBody['AuthCode'] ?? $decodedBody['authCode'] ?? null;
        $errorCode = $decodedBody['ErrorCode'] ?? $decodedBody['errorCode'] ?? null;

        $isSuccessful = in_array((string) $result, ['Success'], true);

        return [
            'success' => $isSuccessful,
            'message' => $message ? (string) $message : null,
            'auth_code' => $authCode ? (string) $authCode : null,
            'error_code' => $errorCode ? (string) $errorCode : null,
            'raw' => is_array($decodedBody) ? $decodedBody : null,
        ];
    }
}
