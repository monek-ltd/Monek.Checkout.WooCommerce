<?php

/**
 * Class WebhookPayload - represents the payload of the webhook from the payment gateway
 *
 * @package Monek
 */
class WebhookPayload 
{
    public $transaction_date_time;
    public $payment_reference;
    public $cross_reference;
    public $response_code;
    public $message;
    public $amount;
    public $currency_code;
    public $integrity_digest;

    /**
     * @param array $data
     */
    public function __construct(array $data) 
    {
        $this->transaction_date_time = sanitize_text_field($data['transactionDateTime'] ?? '');
        $this->payment_reference = sanitize_text_field($data['paymentReference'] ?? '');
        $this->cross_reference = sanitize_text_field($data['crossReference'] ?? '');
        $this->response_code = sanitize_text_field($data['responseCode'] ?? '');
        $this->message = sanitize_text_field($data['message'] ?? '');
        $this->amount = sanitize_text_field($data['amount'] ?? '');
        $this->currency_code = sanitize_text_field($data['currencyCode'] ?? '');
        $this->integrity_digest = sanitize_text_field($data['integrityDigest'] ?? '');
    }

    /**
     * Validate the webhook payload
     *
     * @return bool
     */
    public function validate() : bool 
    {
        return isset($this->transaction_date_time)
            && isset($this->payment_reference)
            && isset($this->cross_reference)
            && isset($this->response_code)
            && isset($this->message)
            && isset($this->amount)
            && isset($this->currency_code)
            && isset($this->integrity_digest)
            && is_numeric($this->amount)
            && preg_match('/^[0-9]{2}$/', $this->response_code);
    }
}
