<?php

namespace Monek\Checkout\Application\Checkout;
use Monek\Checkout\Infrastructure\Logging\Logger;

class ApplePayFileInstaller
{
    private Logger $logger;
   
    public const string APPLE_PAY_FILE_NAME = 'apple-developer-merchantid-domain-association';
    private const string APPLE_PAY_FILE_URL = 'https://cdn.monek.com/apple-pay/' . self::APPLE_PAY_FILE_NAME;

    public function __construct( ?Logger $logger = null )
    {
        $this->logger = $logger ?? new Logger();
    }

    public function downloadFile(): bool
    {
        $this->logger->info('[Monek Checkout] Starting Apple Pay domain file installation' );

        $dir = ABSPATH . '.well-known/';

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tmp_file = download_url( self::APPLE_PAY_FILE_URL );

        if ( is_wp_error( $tmp_file ) ) {
            $this->logger->error('[Monek Checkout] Apple Pay file download failed: ' . $tmp_file->get_error_message());
         
            update_option( 'monek_apple_pay_install_failed', true );
            return false;
        }

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                $this->logger->error('[Monek] Failed to create .well-known directory');

                wp_delete_file( $tmp_file );
                return false;
            }
        }

        $target_file = $dir . self::APPLE_PAY_FILE_NAME;

        $wp_filesystem->move( $tmp_file, $target_file, true );
        $wp_filesystem->chmod( $target_file, 0644 );

        $this->logger->info('[Monek Checkout] Apple Pay domain file installed successfully' );
        return true;
    }
}