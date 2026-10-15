<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;
use Ocs\Migrate\MigrationException;

/** oc_layout_route / oc_layout_module (SPEC-005 REQ-DB-025). */
final class Layouts
{
    private const POSITIONS = ['content_top', 'content_bottom', 'column_left', 'column_right'];

    /** @var Context */
    private $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    public function idByRoute(string $route, int $store = 0): int
    {
        $rows = $this->ctx->rows('SELECT DISTINCT layout_id FROM `' . $this->ctx->table('layout_route') . '` WHERE store_id = ? AND route = ?', [$store, $route]);
        if (count($rows) !== 1) {
            throw new MigrationException(count($rows) ? "route '{$route}' maps to several layouts" : "no layout for route '{$route}'");
        }
        return (int) $rows[0]['layout_id'];
    }

    public function routeAdd(int $layoutId, string $route, int $store = 0): string
    {
        $t = '`' . $this->ctx->table('layout_route') . '`';
        if ($this->ctx->rows("SELECT layout_route_id FROM {$t} WHERE layout_id = ? AND store_id = ? AND route = ?", [$layoutId, $store, $route])) {
            $this->ctx->log("unchanged layout route {$layoutId} {$route}");
            return 'unchanged';
        }
        $this->ctx->sql("INSERT INTO {$t} (layout_id, store_id, route) VALUES (?, ?, ?)", [$layoutId, $store, $route]);
        $this->ctx->log("created layout route {$layoutId} {$route}");
        return 'created';
    }

    /** @param string $moduleCode "<code>" or "<code>.<module_id>" */
    public function moduleAdd(int $layoutId, string $moduleCode, string $position, int $sort): string
    {
        if (!in_array($position, self::POSITIONS, true)) {
            throw new MigrationException("invalid layout position '{$position}'");
        }
        if (!preg_match('/^[a-z][a-z0-9_]*(\.\d+)?$/', $moduleCode)) {
            throw new MigrationException("invalid module code '{$moduleCode}'");
        }
        $t = '`' . $this->ctx->table('layout_module') . '`';
        $rows = $this->ctx->rows("SELECT layout_module_id, `position`, sort_order FROM {$t} WHERE layout_id = ? AND `code` = ? ORDER BY layout_module_id", [$layoutId, $moduleCode]);
        if (!$rows) {
            $this->ctx->sql("INSERT INTO {$t} (layout_id, `code`, `position`, sort_order) VALUES (?, ?, ?, ?)", [$layoutId, $moduleCode, $position, $sort]);
            $this->ctx->log("created layout module {$layoutId} {$moduleCode} {$position}");
            return 'created';
        }
        $result = 'unchanged';
        if ((string) $rows[0]['position'] !== $position || (int) $rows[0]['sort_order'] !== $sort) {
            $this->ctx->sql("UPDATE {$t} SET `position` = ?, sort_order = ? WHERE layout_module_id = ?", [$position, $sort, (int) $rows[0]['layout_module_id']]);
            $result = 'updated';
        }
        if (count($rows) > 1) {
            $this->ctx->sql("DELETE FROM {$t} WHERE layout_id = ? AND `code` = ? AND layout_module_id > ?", [$layoutId, $moduleCode, (int) $rows[0]['layout_module_id']]);
            $result = 'updated';
        }
        $this->ctx->log("{$result} layout module {$layoutId} {$moduleCode}");
        return $result;
    }
}
