<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;
use Ocs\Migrate\MigrationException;

/** Installs OCMOD XML into oc_modification keyed by <code> (SPEC-005 REQ-DB-027, SPEC-007). */
final class Ocmod
{
    /** @var Context */
    private $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    /** @return string 'created'|'updated'|'unchanged' */
    public function install(string $xmlPath): string
    {
        $path = is_file($xmlPath) ? $xmlPath : $this->ctx->repoRoot . '/' . ltrim($xmlPath, '/');
        if (!is_file($path)) {
            throw new MigrationException("OCMOD file not found: {$xmlPath}");
        }
        $xml = (string) file_get_contents($path);
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        if ($doc === false || $doc->getName() !== 'modification') {
            throw new MigrationException("OCMOD file is not a valid <modification> document: {$xmlPath}");
        }
        $meta = [];
        foreach (['code', 'name', 'author', 'version', 'link'] as $field) {
            $meta[$field] = trim((string) $doc->{$field});
            if ($field !== 'link' && $meta[$field] === '') {
                throw new MigrationException("OCMOD <{$field}> is required: {$xmlPath}");
            }
        }
        if (!preg_match('/^ocs_[a-z0-9_]+$/', $meta['code']) || $meta['code'] . '.ocmod.xml' !== basename($path)) {
            throw new MigrationException("OCMOD <code> must be ocs_* and equal the file stem: {$xmlPath}");
        }
        $t = '`' . $this->ctx->table('modification') . '`';
        if ($this->ctx->ocmodDir !== null) {
            return $this->installFile($xml, $meta['code'], $t);
        }
        $rows = $this->ctx->rows("SELECT modification_id, xml, `name`, author, version, link, `status` FROM {$t} WHERE `code` = ? ORDER BY modification_id", [$meta['code']]);
        if (!$rows) {
            $this->ctx->sql(
                "INSERT INTO {$t} (extension_install_id, `name`, `code`, author, version, link, xml, `status`, date_added) VALUES (0, ?, ?, ?, ?, ?, ?, 1, ?)",
                [$meta['name'], $meta['code'], $meta['author'], $meta['version'], $meta['link'], $xml, date('Y-m-d H:i:s')]
            );
            $this->ctx->refreshRequired = true;
            $this->ctx->log("created OCMOD {$meta['code']}");
            return 'created';
        }
        $r = $rows[0];
        $same = (string) $r['xml'] === $xml && (int) $r['status'] === 1 && (string) $r['name'] === $meta['name']
            && (string) $r['author'] === $meta['author'] && (string) $r['version'] === $meta['version'] && (string) $r['link'] === $meta['link'];
        if ($same && count($rows) === 1) {
            $this->ctx->log("unchanged OCMOD {$meta['code']}");
            return 'unchanged';
        }
        $this->ctx->sql(
            "UPDATE {$t} SET `name` = ?, author = ?, version = ?, link = ?, xml = ?, `status` = 1 WHERE modification_id = ?",
            [$meta['name'], $meta['author'], $meta['version'], $meta['link'], $xml, (int) $r['modification_id']]
        );
        if (count($rows) > 1) {
            $this->ctx->sql("DELETE FROM {$t} WHERE `code` = ? AND modification_id > ?", [$meta['code'], (int) $r['modification_id']]);
        }
        $this->ctx->refreshRequired = true;
        $this->ctx->log("updated OCMOD {$meta['code']}");
        return 'updated';
    }

    /** SPEC-053 REQ-OCM-001/002: the XML lives in system/<code>.ocmod.xml; a leftover DB row would apply it twice. */
    private function installFile(string $xml, string $code, string $t): string
    {
        $dir = rtrim((string) $this->ctx->ocmodDir, '/\\');
        $file = $dir . '/' . $code . '.ocmod.xml';
        $dropped = $this->ctx->sql("DELETE FROM {$t} WHERE `code` = ?", [$code]);
        if (is_file($file) && (string) file_get_contents($file) === $xml) {
            $state = 'unchanged';
        } else {
            $state = is_file($file) ? 'updated' : 'created';
            if ((!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) || @file_put_contents($file, $xml) === false) {
                throw new MigrationException("cannot write OCMOD file: {$file}");
            }
        }
        if ($state !== 'unchanged' || $dropped) {
            $this->ctx->refreshRequired = true;
        }
        $this->ctx->log("{$state} OCMOD file {$code}");
        return $state;
    }

    public function remove(string $code): int
    {
        if (strpos($code, 'ocs_') !== 0) {
            throw new MigrationException('only ocs_* OCMODs can be removed by migrations');
        }
        if ($this->ctx->ocmodDir !== null) {
            $file = rtrim((string) $this->ctx->ocmodDir, '/\\') . '/' . $code . '.ocmod.xml';
            if (is_file($file)) {
                @unlink($file);
                $this->ctx->refreshRequired = true;
            }
        }
        $n = $this->ctx->sql('DELETE FROM `' . $this->ctx->table('modification') . '` WHERE `code` = ?', [$code]);
        $this->ctx->refreshRequired = true;
        return $n;
    }
}
