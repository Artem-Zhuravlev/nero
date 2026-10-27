<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\SuperSearch;
use Ocs\SuperSearchIndex;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_super_search/upload/system/library/ocs/supersearch.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_super_search/upload/system/library/ocs/supersearchindex.php';

/** Core-like DB object over the SQLite connection of SqliteDb (query/escape/countAffected, rows/row/num_rows). */
final class SuperSearchFakeDb
{
    /** @var \PDO */
    private $pdo;
    /** @var int */
    private $affected = 0;
    /** @var string[] */
    public $log = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function query(string $sql): \stdClass
    {
        $this->log[] = $sql;
        $st = $this->pdo->query($sql);
        $this->affected = $st->rowCount();
        $rows = $st->columnCount() > 0 ? $st->fetchAll() : [];
        $r = new \stdClass();
        $r->rows = $rows;
        $r->row = $rows ? $rows[0] : [];
        $r->num_rows = count($rows);
        return $r;
    }

    public function escape(string $v): string
    {
        return str_replace("'", "''", $v);
    }

    public function countAffected(): int
    {
        return $this->affected;
    }
}

final class SuperSearchFakeCache
{
    /** @var array<string, mixed> */
    public $data = [];

    public function get($key)
    {
        return $this->data[$key] ?? false;
    }

    public function set($key, $value): void
    {
        $this->data[$key] = $value;
    }
}

final class SuperSearchTest extends TestCase
{
    /** @var SuperSearchFakeDb */
    private $db;
    /** @var SuperSearchFakeCache */
    private $cache;
    /** @var SuperSearchIndex */
    private $index;
    /** @var SqliteDb */
    private $sqlite;

    private function opts(array $extra = []): array
    {
        return $extra + ['store_id' => 0, 'generation' => '1', 'ttl' => 300, 'layout' => 1, 'translit' => 1, 'typo' => 1, 'synonyms' => ''];
    }

    private function boot(): void
    {
        $this->sqlite = new SqliteDb();
        $this->sqlite->createCoreSubset();
        $m = new Context($this->sqlite, 'oc_', dirname(__DIR__, 3));
        (require dirname(__DIR__, 3) . '/custom/extensions/ocs_super_search/db/001_install.php')($m);

        $pdo = $this->sqlite->pdo;
        foreach ([
            'CREATE TABLE oc_product (product_id INTEGER PRIMARY KEY, model TEXT, sku TEXT, mpn TEXT, manufacturer_id INTEGER DEFAULT 0, status INTEGER, date_available TEXT, price REAL DEFAULT 0, sort_order INTEGER DEFAULT 0)',
            'CREATE TABLE oc_product_description (product_id INTEGER, language_id INTEGER, name TEXT, tag TEXT DEFAULT \'\', meta_keyword TEXT DEFAULT \'\')',
            'CREATE TABLE oc_product_to_store (product_id INTEGER, store_id INTEGER)',
            'CREATE TABLE oc_product_to_category (product_id INTEGER, category_id INTEGER)',
            'CREATE TABLE oc_category_description (category_id INTEGER, language_id INTEGER, name TEXT)',
            'CREATE TABLE oc_manufacturer (manufacturer_id INTEGER PRIMARY KEY, name TEXT)',
        ] as $ddl) {
            $pdo->exec($ddl);
        }
        $pdo->exec("INSERT INTO oc_manufacturer VALUES (1, 'Apple'), (2, 'Samsung')");
        $pdo->exec("INSERT INTO oc_category_description VALUES (10, 1, 'Смартфони'), (10, 2, 'Smartphones'), (20, 1, 'Одяг')");

        $products = [
            [1, 'A3090', 1, 1, [['Apple iPhone 15', 2], ['Apple айфон 15', 1]], 10],
            [2, 'JKT-77', 0, 1, [['Одяг чоловічий куртка', 1], ["Men's jacket", 2]], 20],
            [3, 'SM-S921B', 2, 1, [['Samsung Galaxy S24', 2]], 10],
            [4, 'AW-1', 1, 0, [['Apple Watch', 2]], 10],
            [5, 'DOM-1', 0, 1, [['Одежда для дома', 1]], 30],
        ];
        foreach ($products as [$id, $model, $brand, $status, $names, $cat]) {
            $pdo->exec("INSERT INTO oc_product (product_id, model, sku, mpn, manufacturer_id, status, date_available) VALUES ({$id}, '{$model}', '', '', {$brand}, {$status}, '2020-01-01')");
            $pdo->exec("INSERT INTO oc_product_to_store VALUES ({$id}, 0)");
            $pdo->exec("INSERT INTO oc_product_to_category VALUES ({$id}, {$cat})");
            foreach ($names as [$name, $lang]) {
                $pdo->exec("INSERT INTO oc_product_description (product_id, language_id, name) VALUES ({$id}, {$lang}, '" . str_replace("'", "''", $name) . "')");
            }
        }

        $this->db = new SuperSearchFakeDb($pdo);
        $this->cache = new SuperSearchFakeCache();
        $this->index = new SuperSearchIndex($this->db, 'oc_', $this->cache);
        $this->index->reindex([1, 2, 3, 4, 5]);
    }

    // TEST-SSR-001 - verifies REQ-SSR-002, REQ-SSR-005 (tokens and wrong-layout conversion)
    public function test_tokens_and_layout_variants(): void
    {
        $this->assertSame(['мясо', 'mba13', 'm5', 'еж'], SuperSearch::tokens("М'ясо, MBA13-M5 ёж!"));
        $this->assertSame(['привет'], SuperSearch::layoutVariants('ghbdtn'));
        $this->assertSame(['apple'], SuperSearch::layoutVariants('фззду'));
        $this->assertSame([], SuperSearch::layoutVariants('apple 15 фз'), 'mixed scripts are not converted');
        $this->assertSame('ghbdtn cdtn', SuperSearch::layoutVariants('привет свет')[0]);
    }

    // TEST-SSR-002 - verifies REQ-SSR-003 (one phonetic key for Latin, ru and uk spellings)
    public function test_phonetic_key(): void
    {
        foreach (['apple', 'aple', 'эпл', 'аппле', 'епл'] as $w) {
            $this->assertSame('apl', SuperSearch::phonetic($w), $w);
        }
        $this->assertSame(SuperSearch::phonetic('яблуко'), SuperSearch::phonetic('yabluko'));
        $this->assertSame(SuperSearch::phonetic('яблуко'), SuperSearch::phonetic('яблоко'));
        $this->assertSame(SuperSearch::phonetic('одяг'), SuperSearch::phonetic('одиг'));
        $this->assertSame(SuperSearch::phonetic('iphone'), SuperSearch::phonetic('айфон'));
        $this->assertTrue(SuperSearch::phonetic('samsung') !== SuperSearch::phonetic('apple'));
    }

    // TEST-SSR-003 - verifies REQ-SSR-004 (exactly one edit: insert, delete, replace, swap)
    public function test_within_one_edit(): void
    {
        $this->assertTrue(SuperSearch::withinOneEdit('apple', 'aple'));
        $this->assertTrue(SuperSearch::withinOneEdit('одяг', 'одиг'));
        $this->assertTrue(SuperSearch::withinOneEdit('galaxy', 'galxay'), 'neighbour swap');
        $this->assertTrue(SuperSearch::withinOneEdit('galaxy', 'galaxi'));
        $this->assertTrue(!SuperSearch::withinOneEdit('galaxy', 'glxy'), 'two edits');
        $this->assertTrue(!SuperSearch::withinOneEdit('apple', 'abcde'));
    }

    // TEST-SSR-004 - verifies REQ-SSR-007 (synonym syntax, errors, phrase slots)
    public function test_synonyms(): void
    {
        $p = SuperSearch::parseSynonyms("Одежда=шмотки=одяг,носки=гольфы\nbroken; верхняя одежда=куртки");
        $this->assertSame([['одежда', 'шмотки', 'одяг'], ['носки', 'гольфы'], ['верхняя одежда', 'куртки']], $p['groups']);
        $this->assertSame(['broken'], $p['errors']);

        $slots = SuperSearch::slots(['верхняя', 'одежда', 'зима'], $p['groups']);
        $this->assertCount(2, $slots);
        $this->assertSame(['верхняя', 'одежда'], $slots[0]['words']);
        $this->assertTrue(in_array('куртки', $slots[0]['syn'], true));
        $this->assertSame([], $slots[1]['syn']);

        $single = SuperSearch::slots(['шмотки'], $p['groups']);
        $this->assertTrue(in_array('одежда', $single[0]['syn'], true) && in_array('одяг', $single[0]['syn'], true));
    }

    // TEST-SSR-005 - verifies REQ-SSR-010, REQ-SSR-022 (migration: tables, settings, events, idempotent)
    public function test_install_migration(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_super_search/db/001_install.php';
        $fn($m);
        $fn($m);

        $this->assertTrue($db->tableExists('oc_ocs_search_token'));
        $this->assertTrue($db->tableExists('oc_ocs_search_vocab'));
        $this->assertTrue($m->extension->isRegistered('module', 'ocs_super_search'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_super_search', 'module_ocs_super_search_status'));
        $this->assertSame('', (string) $m->setting->get('module_ocs_super_search', 'module_ocs_super_search_built_at'));
        $rows = $db->pdo->query("SELECT `trigger` FROM oc_event WHERE code LIKE 'ocs_super_search_%' ORDER BY code")->fetchAll();
        $this->assertCount(5, $rows);
        $this->assertTrue(in_array('catalog/model/catalog/product/getProducts/before', array_column($rows, 'trigger'), true));
        $this->assertTrue(in_array('admin/model/catalog/product/deleteProduct/after', array_column($rows, 'trigger'), true));
    }

    // TEST-SSR-006 - verifies REQ-SSR-001, REQ-SSR-022 (all languages, model, brand, category in one index; incremental update and delete)
    public function test_index_covers_languages_and_follows_changes(): void
    {
        $this->boot();

        $this->assertSame([1], $this->index->search('айфон', $this->opts(['translit' => 0, 'typo' => 0, 'layout' => 0])), 'name of another language');
        $this->assertSame([1], $this->index->search('a3090', $this->opts()), 'model');
        $this->assertSame([3], $this->index->search('smartphones samsung', $this->opts()), 'category (en) and brand');
        $this->assertSame([1, 3], $this->index->search('смартфони', $this->opts()), 'category (uk)');
        $this->assertSame([3], $this->index->search('sm-s921', $this->opts()), 'model prefix, split on the dash');
        $this->assertSame([3], $this->index->search('smS921b', $this->opts(['typo' => 0, 'translit' => 0])), 'compact form is indexed');

        $this->sqlite->pdo->exec("UPDATE oc_product_description SET name = 'Google Pixel' WHERE product_id = 3");
        $this->index->reindex([3]);
        $opts = $this->opts(['generation' => '2']);
        $this->assertSame([3], $this->index->search('pixel', $opts));
        $this->assertSame([], $this->index->search('galaxy', $opts), 'old words are gone');

        $this->sqlite->pdo->exec('DELETE FROM oc_product WHERE product_id = 3');
        $this->sqlite->pdo->exec('DELETE FROM oc_product_description WHERE product_id = 3');
        $this->index->reindex([3]);
        $this->assertSame([], $this->index->search('pixel', $this->opts(['generation' => '3'])));
        $this->assertSame(0, (int) $this->sqlite->pdo->query('SELECT COUNT(*) FROM oc_ocs_search_vocab WHERE term = \'pixel\'')->fetchColumn(), 'vocabulary follows');
    }

    // TEST-SSR-007 - verifies REQ-SSR-002..006, 008 (layout, translit, typo, synonyms, AND, disabled products)
    public function test_search_strategies(): void
    {
        $this->boot();

        foreach (['apple', 'aple', 'эпл', 'аппле', 'фззду', 'APPLE'] as $q) {
            $this->assertSame([1], $this->index->search($q, $this->opts()), $q . ' finds the enabled Apple product only');
        }
        $this->assertSame([1], $this->index->search('айфон', $this->opts()), 'translit to the iphone token');
        $this->assertSame([2], $this->index->search('одиг', $this->opts()), 'one typo in a Cyrillic word');
        $this->assertSame([2], $this->index->search('одяг куртка', $this->opts()), 'every word must match');
        $this->assertSame([], $this->index->search('apple куртка', $this->opts()));
        $this->assertSame([1], $this->index->search('apple 15', $this->opts()));
        $this->assertSame([], $this->index->search('aple', $this->opts(['typo' => 0, 'translit' => 0])), 'strategies switch off');
        $this->assertSame([], $this->index->search('фззду', $this->opts(['layout' => 0, 'translit' => 0, 'typo' => 0])));
        $this->assertSame([], $this->index->search('zzzz', $this->opts()));

        $syn = "одежда=шмотки,носки=гольфы\nверхняя одежда=куртка";
        $this->assertSame([5], $this->index->search('шмотки', $this->opts(['synonyms' => $syn])));
        $this->assertSame([], $this->index->search('шмотки', $this->opts()), 'no synonyms, no match');
        $this->assertSame([2], $this->index->search('одяг', $this->opts(['synonyms' => 'одяг=jacket'])), 'own word still matches');
    }

    // TEST-SSR-008 - verifies REQ-SSR-006 (exact word outranks phonetic and typo matches)
    public function test_ranking_prefers_exact(): void
    {
        $this->boot();
        $this->sqlite->pdo->exec("INSERT INTO oc_product (product_id, model, sku, mpn, manufacturer_id, status, date_available) VALUES (6, 'X', '', '', 0, 1, '2020-01-01')");
        $this->sqlite->pdo->exec('INSERT INTO oc_product_to_store VALUES (6, 0)');
        $this->sqlite->pdo->exec("INSERT INTO oc_product_description (product_id, language_id, name) VALUES (6, 2, 'Aple pie')");
        $this->index->reindex([6]);

        $this->assertSame([1, 6], $this->index->search('apple', $this->opts(['generation' => '9'])), 'exact first, typo match after');
        $this->assertSame([6, 1], $this->index->search('aple', $this->opts(['generation' => '9'])));
    }

    // TEST-SSR-009 - verifies REQ-SSR-020, REQ-SSR-023 (cache; bounded number of queries; indexed token lookup only)
    public function test_cache_and_query_budget(): void
    {
        $this->boot();
        $opts = $this->opts();

        $this->db->log = [];
        $this->index->search('aple', $opts);
        $cold = count($this->db->log);
        $this->assertTrue($cold <= 6, 'cold query uses ' . $cold . ' statements');
        foreach ($this->db->log as $sql) {
            $this->assertTrue(strpos($sql, 'product_description') === false, 'no scan of product descriptions per search');
            $this->assertTrue(strpos($sql, "LIKE '%") === false, 'no leading wildcard');
        }

        $this->db->log = [];
        $this->assertSame([1], $this->index->search('aple', $opts));
        $this->assertSame([], $this->db->log, 'identical query is answered from the cache');

        $this->db->log = [];
        $this->index->search('aple', $this->opts(['generation' => '77']));
        $this->assertTrue(count($this->db->log) > 0, 'new generation invalidates the cache');

        $this->db->log = [];
        $this->index->search(str_repeat('a', 300) . ' ' . implode(' ', range(100, 130)), $this->opts());
        $this->assertTrue(count($this->db->log) <= 8, 'long queries are cut');
    }

    // TEST-SSR-010 - verifies REQ-SSR-008 (brand filter keeps relevance order; ids of other brands drop out)
    public function test_arrange_filters(): void
    {
        $this->boot();
        $ids = $this->index->search('смартфони', $this->opts());
        $this->assertSame([1, 3], $ids);
        $this->assertSame([3], $this->index->arrange($ids, ['manufacturer_id' => 2, 'sort' => 'p.sort_order', 'order' => 'ASC']));
        $this->assertSame([1, 3], $this->index->arrange($ids, ['sort' => 'p.sort_order']));
        $this->assertSame([3, 1], $this->index->arrange($ids, ['sort' => 'p.model', 'order' => 'DESC', 'language_id' => 1]));
    }
}
