<?php
declare(strict_types=1);

namespace Ocs\Migrate;

/** A discovered migration file (SPEC-005 REQ-DB-010). */
final class Migration
{
    /** @var string e.g. core:0003_stores or ext:ocs_hello:001_install */
    public $id;
    /** @var string absolute path */
    public $path;
    /** @var string 'sql'|'php' */
    public $type;
    /** @var string|null extension code, null for core-level migrations */
    public $ext;
    /** @var string numeric prefix as written (0003 / 001) */
    public $number;
    /** @var string */
    public $slug;

    public function __construct(string $id, string $path, string $type, ?string $ext, string $number, string $slug)
    {
        $this->id = $id;
        $this->path = $path;
        $this->type = $type;
        $this->ext = $ext;
        $this->number = $number;
        $this->slug = $slug;
    }

    public function isUninstall(): bool
    {
        return (bool) preg_match('/(^|_)uninstall$/', $this->slug);
    }

    /** sha256 of the file bytes with CRLF normalised to LF (REQ-DB-003). */
    public function checksum(): string
    {
        return hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($this->path)));
    }
}
