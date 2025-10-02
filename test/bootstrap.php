<?php

declare(strict_types=1);

namespace ImpersonateTest;

require dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    if (strpos($class, 'Doctrine\\') === 0) {
        $file = __DIR__ . '/Stubs/' . str_replace('\\', '/', $class) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
    if ($class === 'Impersonate\\Module') {
        $file = __DIR__ . '/Stubs/Impersonate/Module.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
