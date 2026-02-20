<?php

/**
 * Class Callback - represents the callback from the payment gateway 
 *
 * @package monek
 */
class MCWC_Callback 
{
    public $wp_nonce;
    public $payment_reference;
    public $response_code;
    public $message;

    public function __construct() {
        $this->wp_nonce = filter_input(INPUT_GET, 'WPNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS); 
        $this->payment_reference = filter_input(INPUT_GET, 'paymentreference', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->response_code = filter_input(INPUT_GET, 'responsecode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->message = filter_input(INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
}
