<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;
use Ocs\Migrate\MigrationException;

/**
 * oc_user_group.permission is JSON {"access":[...],"modify":[...]} (plus ocStore's "hiden" key).
 * grant() adds a route to each list if absent and preserves all other keys (SPEC-005 REQ-DB-022).
 */
final class Permissions
{
    /** @var Context */
    private $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    /** @param int|string $group id or name */
    private function groupId($group): int
    {
        return is_int($group) ? $group : $this->ctx->group->id($group);
    }

    /** @return array<string, mixed> */
    private function read(int $id): array
    {
        $rows = $this->ctx->rows('SELECT permission FROM `' . $this->ctx->table('user_group') . '` WHERE user_group_id = ?', [$id]);
        if (!$rows) {
            throw new MigrationException("user group {$id} not found");
        }
        $raw = trim((string) $rows[0]['permission']);
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->ctx->log("warning: invalid permission JSON for group {$id}; treated as empty");
            return [];
        }
        return $data;
    }

    private function write(int $id, array $data): void
    {
        $this->ctx->sql('UPDATE `' . $this->ctx->table('user_group') . '` SET permission = ? WHERE user_group_id = ?', [json_encode($data), $id]);
    }

    /**
     * @param int|string $group
     * @param string[] $types
     * @return string 'updated'|'unchanged'
     */
    public function grant($group, string $route, array $types = ['access', 'modify']): string
    {
        $id = $this->groupId($group);
        $data = $this->read($id);
        $changed = false;
        foreach ($types as $type) {
            $list = isset($data[$type]) && is_array($data[$type]) ? $data[$type] : [];
            if (!in_array($route, $list, true)) {
                $list[] = $route;
                $data[$type] = array_values($list);
                $changed = true;
            }
        }
        if ($changed) {
            $this->write($id, $data);
        }
        $this->ctx->log(($changed ? 'updated' : 'unchanged') . " permission {$route} for group {$id}");
        return $changed ? 'updated' : 'unchanged';
    }

    /** @param int|string $group */
    public function revoke($group, string $route, array $types = ['access', 'modify']): string
    {
        $id = $this->groupId($group);
        $data = $this->read($id);
        $changed = false;
        foreach ($types as $type) {
            if (isset($data[$type]) && is_array($data[$type]) && in_array($route, $data[$type], true)) {
                $data[$type] = array_values(array_diff($data[$type], [$route]));
                $changed = true;
            }
        }
        if ($changed) {
            $this->write($id, $data);
        }
        return $changed ? 'updated' : 'unchanged';
    }

    /** @param int|string $group */
    public function has($group, string $route, string $type = 'access'): bool
    {
        $data = $this->read($this->groupId($group));
        return isset($data[$type]) && is_array($data[$type]) && in_array($route, $data[$type], true);
    }
}
