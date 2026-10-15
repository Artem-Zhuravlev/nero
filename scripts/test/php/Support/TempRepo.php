<?php
declare(strict_types=1);

namespace Ocs\Test\Support;

/** Throw-away repo layout in the system temp dir for runner/discovery tests. */
final class TempRepo
{
    /** @var string */
    public $root;

    public function __construct()
    {
        $this->root = sys_get_temp_dir() . '/ocs-php-test-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/custom/db/migrations', 0777, true);
        mkdir($this->root . '/custom/extensions', 0777, true);
        mkdir($this->root . '/core/upload/admin/controller/extension/module', 0777, true);
        mkdir($this->root . '/core/upload/catalog/controller/common', 0777, true);
        file_put_contents($this->root . '/core/upload/catalog/controller/common/header.php', '<?php');
    }

    public function put(string $rel, string $content): string
    {
        $path = $this->root . '/' . $rel;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
        return $path;
    }

    public function migration(string $name, string $content): string
    {
        return $this->put('custom/db/migrations/' . $name, $content);
    }

    public function extMigration(string $ext, string $name, string $content): string
    {
        return $this->put('custom/extensions/' . $ext . '/db/' . $name, $content);
    }

    public function cleanup(): void
    {
        $this->rm($this->root);
    }

    private function rm(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    $this->rm($path . '/' . $f);
                }
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
}
