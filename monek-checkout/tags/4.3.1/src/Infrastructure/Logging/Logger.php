<?php

namespace Monek\Checkout\Infrastructure\Logging;

use WC_Logger;

class Logger
{
    private const SOURCE = 'monek';

    public function log(string $level, string $message, array $context = []): void
    {
        if (! function_exists('wc_get_logger')) {
            $this->fallbackLog($message, $context);
            return;
        }

        $logger = wc_get_logger();
        if (! $logger instanceof WC_Logger) {
            $this->fallbackLog($message, $context);
            return;
        }

        $logger->log($level, $message, ['source' => self::SOURCE] + $this->normaliseContext($context));
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function normaliseContext(array $context): array
    {
        $normalised = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalised[$key] = $value;
                continue;
            }

            $normalised[$key] = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $normalised;
    }

    private function fallbackLog(string $message, array $context = []): void
    {
        $contextMessage = $context === [] ? '' : ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        error_log('[monek] ' . $message . $contextMessage);
    }
}
