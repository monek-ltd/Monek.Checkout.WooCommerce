<?php

namespace Monek\Checkout\Application\Checkout;

class ApplePayFileInstaller
{
    public function installFile(): bool
    {
        $url = 'https://cdn.monek.com/apple-pay/apple-developer-merchantid-domain-association';
        $dir = ABSPATH . '.well-known/';

        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                error_log('[Monek] Failed to create .well-known directory');
                return false;
            }
        }

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            error_log('[Monek] Failed to fetch Apple Pay file');
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $file = $dir . 'apple-developer-merchantid-domain-association';

        if ( file_put_contents( $file, $body ) === false ) {
            error_log('[Monek] Failed to write Apple Pay file');
            return false;
        }

        @chmod( $file, 0644 );

        error_log('[Monek] Apple Pay file installed at ' . $file);
        return true;
    }
}