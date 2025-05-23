<?php

/**
 * This class is responsible for confirming the integrity of the transaction data
 * 
 * @package Monek
 */
class MCWC_IntegrityCorroborator 
{
    private $is_test_mode_active;

    /**
     * @param bool $is_test_mode_active
     */
    public function __construct(bool $is_test_mode_active) 
    {
        $this->is_test_mode_active = $is_test_mode_active;
    }    
    
    /**
     * Confirm the integrity of the transaction data through an HTTP POST request to the Monek API 
     *
     * @param WC_Order $order
     * @param MCWC_WebhookPayload $transaction_webhook_payload_data
     * @return array|WP_Error
     */
    public function mcwc_confirm_integrity_digest(WC_Order $order, MCWC_WebhookPayload $transaction_webhook_payload_data)
    {
        $idempotency_token = sanitize_text_field(get_post_meta($order->get_id(), 'idempotency_token', true));
        $integrity_secret = sanitize_text_field(get_post_meta($order->get_id(), 'integrity_secret', true));

        $integrity_check_url = esc_url($this->mcwc_get_integrity_check_url());

        return wp_remote_post($integrity_check_url, [
            'body' => http_build_query([
                'IntegritySecret' => $integrity_secret,
                'IntegrityDigest' => $transaction_webhook_payload_data->integrity_digest,
                'RequestTime' => $transaction_webhook_payload_data->transaction_date_time,
                'IdempotencyToken' => $idempotency_token,
                'PaymentReference' => $transaction_webhook_payload_data->payment_reference,
                'CrossReference' => $transaction_webhook_payload_data->cross_reference,
                'ResponseCode' => $transaction_webhook_payload_data->response_code,
                'ResponseMessage' => $transaction_webhook_payload_data->message,
                'Amount' => $transaction_webhook_payload_data->amount,
                'CurrencyCode' => $transaction_webhook_payload_data->currency_code
            ]),
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
    }
    
    /**
     * Get the URL for the integrity check endpoint 
     *
     * @return string
     */
    private function mcwc_get_integrity_check_url(): string
    {
        $integrity_check_extension = 'IntegrityCheck.ashx';
        return ($this->is_test_mode_active ? MCWC_MonekGateway::$staging_url : MCWC_MonekGateway::$elite_url) . $integrity_check_extension;
    }
}
