<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;
use Ocs\Migrate\MigrationException;

/**
 * oc_event registration with validation (SPEC-005 REQ-DB-023, SPEC-007 REQ-OCM-010/011).
 * The table is MyISAM with no constraints, so a wrong trigger would fail silently at runtime.
 */
final class Events
{
    private const TRIGGER = '#^(catalog|admin)/(controller|model|view|language)/[a-zA-Z0-9_/*?]+/(before|after)$#';

    /** @var Context */
    private $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    public static function validateTrigger(string $trigger): void
    {
        if (!preg_match(self::TRIGGER, $trigger, $m)) {
            throw new MigrationException("invalid event trigger '{$trigger}'");
        }
        if ((strpos($trigger, '*') !== false || strpos($trigger, '?') !== false) && !in_array($m[2], ['controller', 'view'], true)) {
            throw new MigrationException("wildcards are only allowed in controller/view triggers: '{$trigger}'");
        }
    }

    /** Does a controller file exist for $route under $app (core or any custom extension)? */
    public function routeFileExists(string $app, string $route): bool
    {
        $parts = explode('/', preg_replace('/[^a-zA-Z0-9_\/]/', '', $route));
        while ($parts) {
            $rel = $app . '/controller/' . implode('/', $parts) . '.php';
            if ($this->ctx->routeResolver !== null) {
                if (($this->ctx->routeResolver)($app, $rel)) {
                    return true;
                }
                array_pop($parts);
                continue;
            }
            if (is_file($this->ctx->repoRoot . '/core/upload/' . $rel)) {
                return true;
            }
            foreach (glob($this->ctx->repoRoot . '/custom/extensions/*/upload/' . $rel) ?: [] as $hit) {
                return $hit !== '';
            }
            array_pop($parts);
        }
        return false;
    }

    /** @return string 'created'|'updated'|'unchanged' */
    public function register(string $code, string $trigger, string $action, int $sort = 0, int $status = 1): string
    {
        if (strpos($code, 'ocs_') !== 0 || strlen($code) > 64 || !preg_match('/^[a-z0-9_]+$/', $code)) {
            throw new MigrationException("event code '{$code}' must match ocs_[a-z0-9_]* and be <= 64 chars");
        }
        self::validateTrigger($trigger);
        $app = substr($trigger, 0, strpos($trigger, '/'));
        if (!$this->routeFileExists($app, $action)) {
            throw new MigrationException("event action '{$action}' has no {$app} controller file in core/ or custom/extensions/");
        }
        $t = '`' . $this->ctx->table('event') . '`';
        $rows = $this->ctx->rows("SELECT event_id, `action`, `status`, sort_order FROM {$t} WHERE `code` = ? AND `trigger` = ? ORDER BY event_id", [$code, $trigger]);
        if (!$rows) {
            $this->ctx->sql("INSERT INTO {$t} (`code`, `trigger`, `action`, `status`, sort_order) VALUES (?, ?, ?, ?, ?)", [$code, $trigger, $action, $status, $sort]);
            $this->ctx->refreshRequired = true;
            $this->ctx->log("created event {$code} {$trigger}");
            return 'created';
        }
        $first = $rows[0];
        $result = 'unchanged';
        if ((string) $first['action'] !== $action || (int) $first['status'] !== $status || (int) $first['sort_order'] !== $sort) {
            $this->ctx->sql("UPDATE {$t} SET `action` = ?, `status` = ?, sort_order = ? WHERE event_id = ?", [$action, $status, $sort, (int) $first['event_id']]);
            $result = 'updated';
        }
        if (count($rows) > 1) {
            $this->ctx->sql("DELETE FROM {$t} WHERE `code` = ? AND `trigger` = ? AND event_id > ?", [$code, $trigger, (int) $first['event_id']]);
            $result = 'updated';
        }
        if ($result !== 'unchanged') {
            $this->ctx->refreshRequired = true;
        }
        $this->ctx->log("{$result} event {$code} {$trigger}");
        return $result;
    }

    public function unregisterByCode(string $code): int
    {
        if (strpos($code, 'ocs_') !== 0) {
            throw new MigrationException('only ocs_* events can be unregistered by migrations');
        }
        $n = $this->ctx->sql('DELETE FROM `' . $this->ctx->table('event') . '` WHERE `code` = ?', [$code]);
        $this->ctx->refreshRequired = true;
        $this->ctx->log("deleted {$n} events with code {$code}");
        return $n;
    }
}
