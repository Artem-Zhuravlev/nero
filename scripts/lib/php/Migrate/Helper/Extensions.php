<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;
use Ocs\Migrate\MigrationException;

/** oc_extension(type, code): register/unregister without touching admin UI (SPEC-005 REQ-DB-021). */
final class Extensions
{
    private const TYPES = ['module', 'payment', 'shipping', 'total', 'theme', 'dashboard', 'report', 'feed', 'captcha', 'currency', 'fraud', 'analytics', 'advertise'];

    /** @var Context */
    private $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    public function register(string $type, string $code): string
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new MigrationException("unknown extension type {$type}");
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $code)) {
            throw new MigrationException("invalid extension code {$code} (max 32 chars, oc_extension.code is varchar(32))");
        }
        if ($this->isRegistered($type, $code)) {
            $this->ctx->log("unchanged extension {$type}/{$code}");
            return 'unchanged';
        }
        $this->ctx->sql('INSERT INTO `' . $this->ctx->table('extension') . '` (`type`, `code`) VALUES (?, ?)', [$type, $code]);
        $this->ctx->refreshRequired = true;
        $this->ctx->log("created extension {$type}/{$code}");
        return 'created';
    }

    public function isRegistered(string $type, string $code): bool
    {
        return (bool) $this->ctx->rows('SELECT extension_id FROM `' . $this->ctx->table('extension') . '` WHERE `type` = ? AND `code` = ?', [$type, $code]);
    }

    public function unregister(string $type, string $code): int
    {
        $n = $this->ctx->sql('DELETE FROM `' . $this->ctx->table('extension') . '` WHERE `type` = ? AND `code` = ?', [$type, $code]);
        $this->ctx->log("deleted {$n} extension rows {$type}/{$code}");
        return $n;
    }
}
