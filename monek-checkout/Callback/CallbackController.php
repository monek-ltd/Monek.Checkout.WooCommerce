<?php

class CallbackController {
    private const GATEWAY_ID = 'monekgateway';
    
    private $integrity_corroborator;

    public function __construct($is_test_mode_active) {
        $this->integrity_corroborator = new IntegrityCorroborator($is_test_mode_active);
    }

    public function register_routes() {
        add_action('woocommerce_api_'.self::GATEWAY_ID, array(&$this, 'handle_callback'));
    }
    
    public function handle_callback()
    {
        $json_echo = file_get_contents('php://input');
        $transaction_webhook_payload_data = json_decode($json_echo, true);

        if(isset($transaction_webhook_payload_data)){
            $this->process_transaction_webhook_payload($transaction_webhook_payload_data);
        }
        else {
            $this->process_payment_callback();
        }
    }

    private function process_payment_callback()
    {
        $callback = new Callback();

        if (!wp_verify_nonce($callback->wp_nonce, 'complete-payment_'.$callback->payment_reference)) {
            return new WP_Error('invalid_nonce', __('Invalid nonce', 'monek-payment-gateway'));
        }

        $order = wc_get_order($callback->payment_reference);
        
        if(!$order){
            global $wp_query;
            $wp_query->set_404();
            status_header(404, 'Order Not Found');
            include( get_query_template( '404' ) );
            exit;
        }
        
        if(!isset($callback->response_code) || $callback->response_code != '00'){
            $note = 'Payment declined: ' . $_REQUEST['message'] ;
            wc_add_notice( $note,'error');
            $order->add_order_note(__('Payment declined', 'monek-payment-gateway'));
            $order->update_status('failed');
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }
   
        $order->add_order_note(__('Awaiting payment confirmation.', 'monek-payment-gateway'));
        WC()->cart->empty_cart();

        $thankyou = $order->get_checkout_order_received_url();
        wp_safe_redirect($thankyou);
    }

    private function process_transaction_webhook_payload($transaction_webhook_payload_data){
        if(filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING) === 'POST') {

            $payload = new WebhookPayload($transaction_webhook_payload_data);

            if(!$payload->validate()){
                header('HTTP/1.1 400 Bad Request');
                echo wp_json_encode(array('error' => 'Bad Request'));
                return;
            }

            $order = wc_get_order($payload->payment_reference);
            if(!$order){
                header('HTTP/1.1 400 Bad Request');
                echo wp_json_encode(array('error' => 'Bad Request'));
                return;
            }

            if($payload->response_code == '00'){
                $saved_integrity_secret = get_post_meta($order->get_id(), 'integrity_secret', true);
                if(!isset($saved_integrity_secret) || $saved_integrity_secret == ''){
                    header('HTTP/1.1 500 Internal Server Error');
                    echo wp_json_encode(array('error' => 'Internal Server Error'));
                    return;
                }

                $response = $this->integrity_corroborator->confirm_integrity_digest($order, $payload);

                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
                    header('HTTP/1.1 400 Bad Request');
                    echo wp_json_encode(array('error' => 'Bad Request'));
                } else {
                    $order->add_order_note(__('Payment confirmed.', 'monek-payment-gateway'));
                    $order->payment_complete();
                }
            }
        }
        else {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            echo wp_json_encode(array('error' => 'Method Not Allowed'));
        }
    }
}
