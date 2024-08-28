<?php

/**
 * Controller to handle the callback from Monek after a payment has been processed.
 *
 * @package Monek
 */
class CallbackController 
{

    private const GATEWAY_ID = 'monek-checkout';
    
    private $integrity_corroborator;

    /**
     * @param bool $is_test_mode_active
     */
    public function __construct(bool $is_test_mode_active) 
    {
        $this->integrity_corroborator = new IntegrityCorroborator($is_test_mode_active);
    }

    /**
     * register the routes for the callback
     *
     * @return void
     */
    public function register_routes() : void
    {
        add_action('woocommerce_api_'.self::GATEWAY_ID, [&$this, 'handle_callback']);
    }
    
    /**
     * handle the callback from Monek after a payment has been processed, either from the payment page or from the webhook
     *
     * @return void
     */
    public function handle_callback() : void
    {
        if(filter_input(INPUT_GET, 'Callback', FILTER_VALIDATE_BOOLEAN) == 'true'){
            $this->process_payment_callback();
        }
        else {
            json_echo = file_get_contents('php://input');
            $transaction_webhook_payload_data = json_decode($json_echo, true);
            $payload = new WebhookPayload($transaction_webhook_payload_data);
            $this->process_transaction_webhook_payload($payload);
        }
    }

    /**
     * process the payment callback from Monek after a payment has been processed 
     *
     * @return void
     */
    private function process_payment_callback() : void
    {
        $callback = new Callback();

        if (!wp_verify_nonce($callback->wp_nonce, "complete-payment_{$callback->payment_reference}")) {
            new WP_Error('invalid_nonce', __('Invalid nonce', 'monek-checkout'));
            return;
        }

        $order = wc_get_order($callback->payment_reference);
        
        if(!$order){
            global $wp_query;
            $wp_query->set_404();
            status_header(404, 'Order Not Found');
            include get_query_template('404');
            exit;
        }
        
        if(!isset($callback->response_code) || $callback->response_code != '00'){
            $note = 'Payment declined: ' . $callback->message ;
            wc_add_notice($note, 'error');
            $order->add_order_note(__('Payment declined', 'monek-checkout'));
            $order->update_status('failed');
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }
   
        $order->add_order_note(__('Awaiting payment confirmation.', 'monek-checkout'));
        WC()->cart->empty_cart();

        $order_complete_url = esc_url($order->get_checkout_order_received_url());
        wp_safe_redirect($order_complete_url);
        exit;
    }

    /**
     * process the post-transaction confirmation webhook payload from Monek after a payment has been processed 
     *
     * @param array $transaction_webhook_payload_data
     * @return void
     */
    private function process_transaction_webhook_payload(WebhookPayload $payload) : void
    {
        if(filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS) === 'POST') {

            if(!$payload->validate()){
                header('HTTP/1.1 400 Bad Request');
                echo wp_json_encode(['error' => 'Bad Request']);
                return;
            }

            $order = wc_get_order($payload->payment_reference);
            if(!$order){
                header('HTTP/1.1 400 Bad Request');
                echo wp_json_encode(['error' => 'Bad Request']);
                return;
            }

            if($payload->response_code == '00'){
                $saved_integrity_secret = get_post_meta($order->get_id(), 'integrity_secret', true);
                if(!isset($saved_integrity_secret) || $saved_integrity_secret == ''){
                    header('HTTP/1.1 500 Internal Server Error');
                    echo wp_json_encode(['error' => 'Internal Server Error']);
                    return;
                }

                $response = $this->integrity_corroborator->confirm_integrity_digest($order, $payload);

                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
                    header('HTTP/1.1 400 Bad Request');
                    echo wp_json_encode(['error' => 'Bad Request']);
                } else {
                    $order->add_order_note(__('Payment confirmed.', 'monek-checkout'));
                    $order->payment_complete();
                }
            }
        }
        else {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            echo wp_json_encode(['error' => 'Method Not Allowed']);
        }
    }
}
