<?php

class IntegrityCorroborator {
    
    private $is_test_mode_active;

    public function __construct($is_test_mode_active) {
        $this->is_test_mode_active = $is_test_mode_active;
    }    
    
    public function confirm_integrity_digest($order, $transaction_webhook_payload_data) {
        $idempotency_token = get_post_meta($order->get_id(), 'idempotency_token', true);
        $integrity_secret = get_post_meta($order->get_id(), 'integrity_secret', true);

        $integrity_check_url = $this->get_integrity_check_url();

        return wp_remote_post($integrity_check_url, array(
            'body' => http_build_query(array(
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
            )),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
    }
    
    
    private function get_integrity_check_url(){
        $integrity_check_extension = 'IntegrityCheck.ashx';
        return ($this->is_test_mode_active ? MonekGateway::$staging_url : MonekGateway::$elite_url) . $integrity_check_extension;
    }
}
