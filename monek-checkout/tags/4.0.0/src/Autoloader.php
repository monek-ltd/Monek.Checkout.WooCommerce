<?php

namespace Monek\Checkout;

final class Autoloader
{
    private const NAMESPACE_PREFIX = 'Monek\\Checkout\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $className): void
    {
        if (strpos($className, self::NAMESPACE_PREFIX) !== 0) {
            return;
        }

        $relativeClass = substr($className, strlen(self::NAMESPACE_PREFIX));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

        if (is_readable($filePath)) {
            require_once $filePath;
        }
    }
}
