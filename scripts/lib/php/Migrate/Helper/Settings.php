<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;

/**
 * oc_setting has no unique key on (store_id, code, key), so writers do per-key upserts and
 * collapse duplicates to the lowest setting_id (SPEC-005 REQ-DB-020, edge cases).
 * Unlike core's editSetting() this never deletes other keys of the same code.
 */
final class Settings
{
    /** @var Context */
    private $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    /** @return string 'created'|'updated'|'unchanged' */
    public function set(string $code, string $key, $value, int $store = 0): string
    {
        $t = '`' . $this->ctx->table('setting') . '`';
        $serialized = is_array($value) || is_object($value);
        $stored = $serialized ? (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        $rows = $this->ctx->rows("SELECT setting_id, `value`, serialized FROM {$t} WHERE store_id = ? AND `code` = ? AND `key` = ? ORDER BY setting_id", [$store, $code, $key]);
        if (!$rows) {
            $this->ctx->sql("INSERT INTO {$t} (store_id, `code`, `key`, `value`, serialized) VALUES (?, ?, ?, ?, ?)", [$store, $code, $key, $stored, $serialized ? 1 : 0]);
            $this->ctx->log("created setting {$code}.{$key} (store {$store})");
            return 'created';
        }
        $first = $rows[0];
        $result = 'unchanged';
        if ((string) $first['value'] !== $stored || (int) $first['serialized'] !== ($serialized ? 1 : 0)) {
            $this->ctx->sql("UPDATE {$t} SET `value` = ?, serialized = ? WHERE setting_id = ?", [$stored, $serialized ? 1 : 0, (int) $first['setting_id']]);
            $result = 'updated';
        }
        if (count($rows) > 1) {
            $this->ctx->sql("DELETE FROM {$t} WHERE store_id = ? AND `code` = ? AND `key` = ? AND setting_id > ?", [$store, $code, $key, (int) $first['setting_id']]);
            $result = 'updated';
        }
        $this->ctx->log("{$result} setting {$code}.{$key} (store {$store})");
        return $result;
    }

    /** @param array<string, mixed> $values key => value */
    public function setMany(string $code, array $values, int $store = 0): void
    {
        foreach ($values as $key => $value) {
            $this->set($code, (string) $key, $value, $store);
        }
    }

    /** @return mixed|null decoded value, or null when missing */
    public function get(string $code, string $key, int $store = 0)
    {
        $t = '`' . $this->ctx->table('setting') . '`';
        $rows = $this->ctx->rows("SELECT `value`, serialized FROM {$t} WHERE store_id = ? AND `code` = ? AND `key` = ? ORDER BY setting_id LIMIT 1", [$store, $code, $key]);
        if (!$rows) {
            return null;
        }
        return (int) $rows[0]['serialized'] === 1 ? json_decode((string) $rows[0]['value'], true) : $rows[0]['value'];
    }

    public function delete(string $code, string $key, int $store = 0): int
    {
        $t = '`' . $this->ctx->table('setting') . '`';
        $n = $this->ctx->sql("DELETE FROM {$t} WHERE store_id = ? AND `code` = ? AND `key` = ?", [$store, $code, $key]);
        $this->ctx->log("deleted {$n} setting rows {$code}.{$key}");
        return $n;
    }
}
