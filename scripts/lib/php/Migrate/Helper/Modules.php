<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;

/** oc_module instances: upsert by (code, name), returns module_id (SPEC-005 REQ-DB-026). */
final class Modules
{
    /** @var Context */
    private $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    /** @param array<string, mixed> $setting */
    public function upsert(string $code, string $name, array $setting): int
    {
        $t = '`' . $this->ctx->table('module') . '`';
        $json = (string) json_encode($setting, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rows = $this->ctx->rows("SELECT module_id, setting FROM {$t} WHERE `code` = ? AND `name` = ? ORDER BY module_id", [$code, $name]);
        if (!$rows) {
            $this->ctx->sql("INSERT INTO {$t} (`name`, `code`, setting) VALUES (?, ?, ?)", [$name, $code, $json]);
            $id = $this->ctx->db->lastId();
            $this->ctx->log("created module instance {$code}/{$name} #{$id}");
            return $id;
        }
        $id = (int) $rows[0]['module_id'];
        if ((string) $rows[0]['setting'] !== $json) {
            $this->ctx->sql("UPDATE {$t} SET setting = ? WHERE module_id = ?", [$json, $id]);
            $this->ctx->log("updated module instance {$code}/{$name} #{$id}");
        } else {
            $this->ctx->log("unchanged module instance {$code}/{$name} #{$id}");
        }
        return $id;
    }
}
