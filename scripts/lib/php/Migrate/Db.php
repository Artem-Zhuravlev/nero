<?php
declare(strict_types=1);

namespace Ocs\Migrate;

/**
 * Minimal database port used by the migration runner, helpers and installer.
 * Production adapter: CoreDb (wraps core's DB class). Tests: an in-memory SQLite adapter.
 */
interface Db
{
    /** 'mysql' or 'sqlite' (tests). */
    public function driver(): string;

    /** @return array<int, array<string, mixed>> */
    public function rows(string $sql): array;

    /** Executes a statement and returns the number of affected rows. */
    public function exec(string $sql): int;

    public function escape(string $value): string;

    public function lastId(): int;

    public function tableExists(string $table): bool;

    public function columnExists(string $table, string $column): bool;

    public function indexExists(string $table, string $index): bool;

    /** Creates the bookkeeping table if missing (SPEC-005 REQ-DB-003). */
    public function ensureMigrationTable(string $table): void;

    /** Advisory lock; false when another runner holds it (REQ-DB-008). */
    public function lock(string $name): bool;

    public function unlock(string $name): void;
}
