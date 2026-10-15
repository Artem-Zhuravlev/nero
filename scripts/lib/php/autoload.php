<?php
declare(strict_types=1);

// Tiny PSR-4-like autoloader for tooling classes: Ocs\Migrate\Foo\Bar -> scripts/lib/php/Migrate/Foo/Bar.php
// (SPEC-005 REQ-DB-070: no Composer).
spl_autoload_register(static function (string $class): void {
    $prefix = 'Ocs\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
