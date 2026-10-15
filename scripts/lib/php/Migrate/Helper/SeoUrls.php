<?php
declare(strict_types=1);

namespace Ocs\Migrate\Helper;

use Ocs\Migrate\Context;
use Ocs\Migrate\MigrationException;

/** oc_seo_url rows: one per (store, language, query), keyword unique per (store, language) (SPEC-005 REQ-DB-024). */
final class SeoUrls
{
    /** @var Context */
    private $ctx;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
    }

    public function set(string $query, string $keyword, string $lang, int $store = 0): string
    {
        if (!preg_match('/^[a-z0-9]+(?:[-\/][a-z0-9]+)*$/', $keyword)) {
            throw new MigrationException("invalid SEO keyword '{$keyword}' (lowercase latin, digits, '-' and '/')");
        }
        $languageId = $this->ctx->lang->id($lang);
        $t = '`' . $this->ctx->table('seo_url') . '`';
        $clash = $this->ctx->rows("SELECT `query` FROM {$t} WHERE store_id = ? AND language_id = ? AND keyword = ? AND `query` <> ?", [$store, $languageId, $keyword, $query]);
        if ($clash) {
            throw new MigrationException("SEO keyword '{$keyword}' is already used by '{$clash[0]['query']}' (store {$store}, {$lang})");
        }
        $rows = $this->ctx->rows("SELECT seo_url_id, keyword FROM {$t} WHERE store_id = ? AND language_id = ? AND `query` = ? ORDER BY seo_url_id", [$store, $languageId, $query]);
        if (!$rows) {
            $this->ctx->sql("INSERT INTO {$t} (store_id, language_id, `query`, keyword) VALUES (?, ?, ?, ?)", [$store, $languageId, $query, $keyword]);
            $this->ctx->log("created seo url {$query} => {$keyword} ({$lang})");
            return 'created';
        }
        $result = 'unchanged';
        if ((string) $rows[0]['keyword'] !== $keyword) {
            $this->ctx->sql("UPDATE {$t} SET keyword = ? WHERE seo_url_id = ?", [$keyword, (int) $rows[0]['seo_url_id']]);
            $result = 'updated';
        }
        if (count($rows) > 1) {
            $this->ctx->sql("DELETE FROM {$t} WHERE store_id = ? AND language_id = ? AND `query` = ? AND seo_url_id > ?", [$store, $languageId, $query, (int) $rows[0]['seo_url_id']]);
            $result = 'updated';
        }
        $this->ctx->log("{$result} seo url {$query} => {$keyword} ({$lang})");
        return $result;
    }

    public function delete(string $query, int $store = 0): int
    {
        return $this->ctx->sql('DELETE FROM `' . $this->ctx->table('seo_url') . '` WHERE store_id = ? AND `query` = ?', [$store, $query]);
    }
}
