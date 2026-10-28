<?php
// Extension: ocs_novaposhta. Minimal database port for the sync and the lookups, so they run on core's DB, the CLI and test doubles. Owner: SPEC-085.

namespace Ocs\Novaposhta;

interface Db {
	/** @return array rows as associative arrays */
	public function rows($sql);

	/** @return int affected rows */
	public function exec($sql);

	public function escape($value);

	/** Advisory lock; false when someone else holds it. */
	public function lock($name);

	public function unlock($name);
}
