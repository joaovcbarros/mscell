<?php

/**
 * Autoloader simples PSR-4-like (sem Composer).
 * Namespace "MsCell\Xxx\..." -> arquivo em src/Xxx/....php
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'MsCell\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
