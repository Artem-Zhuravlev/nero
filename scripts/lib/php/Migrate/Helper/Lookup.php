<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;
use Ocs\Migrate\MigrationException;

/** id lookups that fail loudly instead of guessing (SPEC-005 REQ-DB-028). */
final class Lookup
{
    /** @var Context */
    private $ctx;
    /** @var string */
    private $table;
    /** @var string */
    private $idColumn;
    /** @var string */
    private $keyColumn;
    /** @var string */
    private $label;

    public function __construct(Context $ctx, string $table, string $idColumn, string $keyColumn, string $label)
    {
        $this->ctx = $ctx;
        $this->table = $table;
        $this->idColumn = $idColumn;
        $this->keyColumn = $keyColumn;
        $this->label = $label;
    }

    public function id(string $key): int
    {
        $rows = $this->ctx->rows(
            "SELECT DISTINCT {$this->idColumn} AS id FROM `" . $this->ctx->table($this->table) . "` WHERE {$this->keyColumn} = ?",
            [$key]
        );
        if (count($rows) === 0) {
            throw new MigrationException("{$this->label} '{$key}' not found");
        }
        if (count($rows) > 1) {
            throw new MigrationException("{$this->label} '{$key}' is ambiguous");
        }
        return (int) $rows[0]['id'];
    }
}
