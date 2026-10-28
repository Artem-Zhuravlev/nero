<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

$np = dirname(__DIR__, 3) . '/custom/extensions/ocs_novaposhta/upload/system/library/ocs/novaposhta/';
foreach (['db', 'client', 'text', 'sync', 'repository', 'presets'] as $f) {
    require_once $np . $f . '.php';
}

/** Port implementation over the SQLite test database; counts statements by prefix. */
final class NpTestDb implements \Ocs\Novaposhta\Db
{
    /** @var \PDO */
    public $pdo;
    /** @var string[] */
    public $log = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function rows($sql)
    {
        $this->log[] = $sql;
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function exec($sql)
    {
        $this->log[] = $sql;
        return (int) $this->pdo->exec($sql);
    }

    public function escape($value)
    {
        return str_replace("'", "''", (string) $value);
    }

    public function lock($name)
    {
        if (!empty(SqliteDb::$locks[$name])) {
            return false;
        }
        SqliteDb::$locks[$name] = true;

        return true;
    }

    public function unlock($name)
    {
        unset(SqliteDb::$locks[$name]);
    }

    public function count(string $prefix): int
    {
        return count(array_filter($this->log, static function ($s) use ($prefix) {
            return strpos($s, $prefix) === 0;
        }));
    }
}

final class NovaposhtaTest extends TestCase
{
    private const AREA_KYIV = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const AREA_LVIV = 'aaaaaaaa-0000-0000-0000-000000000002';

    /** @var NpTestDb */
    private $db;
    /** @var array<string, array> directory fixtures */
    private $data;
    /** @var string[] "entity:page" calls the fake API received */
    private $calls = [];
    /** @var string|null "entity:page" at which the fake API fails once */
    private $failAt = null;

    protected function setUp(): void
    {
        $sqlite = new SqliteDb();
        $sqlite->createCoreSubset();
        $m = new Context($sqlite, 'oc_', dirname(__DIR__, 3));
        (require dirname(__DIR__, 3) . '/custom/extensions/ocs_novaposhta/db/001_install.php')($m);
        $this->db = new NpTestDb($sqlite->pdo);
        $this->calls = [];
        $this->failAt = null;

        $cities = [];
        for ($i = 1; $i <= 25; $i++) {
            $cities[] = ['Ref' => sprintf('cccccccc-0000-0000-0000-%012d', $i), 'Description' => 'Місто' . $i, 'DescriptionRu' => 'Город' . $i, 'Area' => self::AREA_KYIV, 'SettlementTypeDescription' => 'місто'];
        }
        $cities[0]['Description'] = 'Київ';
        $cities[0]['DescriptionRu'] = 'Киев';
        $cities[1]['Description'] = 'Київець';
        $cities[1]['DescriptionRu'] = 'Киевец';

        $this->data = [
            'areas' => [
                ['Ref' => self::AREA_KYIV, 'Description' => 'Київська', 'DescriptionRu' => 'Киевская'],
                ['Ref' => self::AREA_LVIV, 'Description' => 'Львівська', 'DescriptionRu' => 'Львовская'],
            ],
            'cities' => $cities,
            'warehouses' => $this->warehouses(),
        ];
    }

    private function warehouses(): array
    {
        $city = 'cccccccc-0000-0000-0000-000000000001';
        $w = function (int $n, string $desc, string $cat = 'Branch', string $status = 'Working') use ($city) {
            return ['Ref' => sprintf('wwwwwwww-0000-0000-0000-%012d', $n), 'CityRef' => $city, 'Number' => (string) $n, 'Description' => $desc, 'DescriptionRu' => $desc, 'CategoryOfWarehouse' => $cat,
                'WarehouseStatus' => $status, 'TotalMaxWeightAllowed' => '30', 'Latitude' => '50.45', 'Longitude' => '30.52', 'Schedule' => ['Monday' => '09:00-20:00']];
        };

        return [
            $w(1, 'Відділення №1: вул. Хрещатик, 1'),
            $w(5, 'Відділення №5: вул. Шевченка, 12'),
            $w(15, 'Відділення №15: вул. Хрещатик, 50'),
            $w(34030, 'Поштомат №34030: вул. Хрещатик, 7', 'Postomat'),
            $w(7, 'Вантажне відділення №7: вул. Промислова, 3'),
            $w(8, 'Відділення №8: закрите', 'Branch', 'Closed'),
        ];
    }

    private function client(): \Ocs\Novaposhta\Client
    {
        $transport = function (string $url, string $body, int $timeout): array {
            $req = json_decode($body, true);
            $entity = ['getAreas' => 'areas', 'getCities' => 'cities', 'getWarehouses' => 'warehouses'][$req['calledMethod']];
            $props = (array) $req['methodProperties'];
            $page = $props['Page'] ?? 1;
            $limit = $props['Limit'] ?? 500;
            $this->calls[] = $entity . ':' . $page;

            if ($this->failAt === $entity . ':' . $page) {
                $this->failAt = null;
                return [200, json_encode(['success' => false, 'errors' => ['API key ' . $req['apiKey'] . ' is wrong']])];
            }

            $slice = $entity === 'areas' ? $this->data[$entity] : array_slice($this->data[$entity], ($page - 1) * $limit, $limit);
            return [200, json_encode(['success' => true, 'data' => $slice, 'errors' => []])];
        };

        return new \Ocs\Novaposhta\Client('SECRET-KEY', $transport, 0, function (): void {
        });
    }

    private function sync(int $limit = 10): \Ocs\Novaposhta\Sync
    {
        return new \Ocs\Novaposhta\Sync($this->db, 'oc_', $this->client(), ['limit' => $limit, 'pause_ms' => 0, 'sleep' => function (): void {
        }]);
    }

    private function scalar(string $sql)
    {
        return $this->db->rows($sql)[0]['c'];
    }

    // TEST-NP-002 - verifies REQ-NP-053, REQ-NP-055 (install migration: tables, disabled by default, shipping extension and events, idempotent)
    public function test_install_migration(): void
    {
        $sqlite = new SqliteDb();
        $sqlite->createCoreSubset();
        $m = new Context($sqlite, 'oc_', dirname(__DIR__, 3));
        $run = function () use ($m): void {
            (require dirname(__DIR__, 3) . '/custom/extensions/ocs_novaposhta/db/001_install.php')($m);
        };
        $run();
        $token = $m->setting->get('shipping_ocs_novaposhta', 'shipping_ocs_novaposhta_cron_token');
        $run();
        foreach (['area', 'city', 'warehouse', 'sync', 'order'] as $t) {
            $this->assertTrue($sqlite->tableExists('oc_ocs_np_' . $t), $t);
        }
        $this->assertSame('0', (string) $m->setting->get('shipping_ocs_novaposhta', 'shipping_ocs_novaposhta_status'));
        $this->assertSame(32, strlen((string) $token));
        $this->assertSame($token, $m->setting->get('shipping_ocs_novaposhta', 'shipping_ocs_novaposhta_cron_token'), 'second run keeps the token');
        $this->assertTrue($m->extension->isRegistered('shipping', 'ocs_novaposhta'));
        $this->assertCount(5, $sqlite->pdo->query("SELECT 1 FROM oc_event WHERE code LIKE 'ocs_np_%'")->fetchAll());
    }

    // TEST-NP-004 - verifies REQ-NP-001, REQ-NP-002, REQ-NP-007 (each directory loads separately and page by page)
    public function test_directories_load_separately_in_pages(): void
    {
        $r = $this->sync()->run('areas');
        $this->assertSame('done', $r['status']);
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_area'));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_city'), 'cities untouched by the areas run');

        $r = $this->sync()->run('cities');
        $this->assertSame('done', $r['status']);
        $this->assertSame(25, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_city'));
        $this->assertSame(['areas:1', 'cities:1', 'cities:2', 'cities:3'], $this->calls, 'three pages of 10/10/5');

        $this->sync()->run('warehouses');
        $this->assertSame(5, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_warehouse'), 'the closed point is not loaded');
        $this->assertSame('branch', $this->scalar("SELECT type AS c FROM oc_ocs_np_warehouse WHERE number = 1"));
        $this->assertSame('postomat', $this->scalar("SELECT type AS c FROM oc_ocs_np_warehouse WHERE number = 34030"));
        $this->assertSame('cargo', $this->scalar("SELECT type AS c FROM oc_ocs_np_warehouse WHERE number = 7"));
        $this->assertSame(4, (int) $this->scalar("SELECT warehouses_count AS c FROM oc_ocs_np_city WHERE name_uk = 'Київ'"), 'cargo is not counted');
        $this->assertSame('done', $this->sync()->state('warehouses')['status']);
    }

    // TEST-NP-004 - verifies REQ-NP-003 (an unchanged rerun writes nothing, a vanished row is deactivated, not deleted)
    public function test_rerun_is_idempotent_and_deactivates_missing(): void
    {
        $this->sync()->run('cities');
        $this->db->log = [];
        $this->sync()->run('cities');
        $this->assertSame(0, $this->db->count('REPLACE INTO `oc_ocs_np_city`'), 'nothing changed, nothing rewritten');
        $this->assertTrue($this->db->count('UPDATE `oc_ocs_np_city` SET run_id') > 0, 'only the run marker moves');

        array_pop($this->data['cities']);
        $this->sync()->run('cities');
        $this->assertSame(25, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_city'), 'row kept');
        $this->assertSame(24, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_city WHERE is_active = 1'));

        // it comes back: reactivated
        $this->data['cities'][] = ['Ref' => sprintf('cccccccc-0000-0000-0000-%012d', 25), 'Description' => 'Місто25', 'DescriptionRu' => 'Город25', 'Area' => self::AREA_KYIV, 'SettlementTypeDescription' => 'місто'];
        $this->sync()->run('cities');
        $this->assertSame(25, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_city WHERE is_active = 1'));
    }

    // TEST-NP-004 - verifies REQ-NP-002, REQ-NP-003, REQ-NP-008 (a failed run resumes from its cursor; a run that lost most rows deactivates nothing)
    public function test_interrupted_run_resumes_and_suspicious_run_is_refused(): void
    {
        $this->failAt = 'cities:2';
        $r = $this->sync()->run('cities');
        $this->assertSame('error', $r['status']);
        $this->assertTrue(strpos($r['error'], 'SECRET-KEY') === false, 'the key never reaches the error text');
        $state = $this->sync()->state('cities');
        $this->assertSame(2, (int) $state['page']);
        $this->assertSame(10, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_city'));

        $this->calls = [];
        $r = $this->sync()->run('cities');
        $this->assertSame('done', $r['status']);
        $this->assertSame(['cities:2', 'cities:3'], $this->calls, 'continues at page 2, page 1 is not fetched again');
        $this->assertSame(25, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_city WHERE is_active = 1'));

        $this->data['cities'] = array_slice($this->data['cities'], 0, 5);
        $r = $this->sync()->run('cities');
        $this->assertSame('error', $r['status']);
        $this->assertSame(25, (int) $this->scalar('SELECT COUNT(*) AS c FROM oc_ocs_np_city WHERE is_active = 1'), 'a truncated response must not wipe the directory');
    }

    // TEST-NP-004 - verifies REQ-NP-004, REQ-NP-002 (a held lock stops a parallel run; budget stops a run with a resumable cursor; dry run writes nothing)
    public function test_lock_budget_and_dry_run(): void
    {
        SqliteDb::$locks['ocs_np_sync_cities'] = true;
        $this->assertSame('locked', $this->sync()->run('cities')['status']);
        unset(SqliteDb::$locks['ocs_np_sync_cities']);

        $now = 1000;
        $sync = new \Ocs\Novaposhta\Sync($this->db, 'oc_', $this->client(), ['limit' => 10, 'pause_ms' => 0, 'clock' => function () use (&$now) {
            return $now += 5;
        }, 'sleep' => function (): void {
        }]);
        $r = $sync->run('cities', 4);
        $this->assertSame('partial', $r['status']);
        $this->assertSame('running', $sync->state('cities')['status']);

        $dry = $this->sync();
        $this->db->log = [];
        $d = $dry->run('areas', 0, true);
        $this->assertSame(2, $d['rows']);
        $this->assertSame(0, $this->db->count('REPLACE'));
    }

    // TEST-NP-006 - verifies REQ-NP-010, REQ-NP-051 (city suggestions: prefix in both languages, bigger cities first, hostile input harmless)
    public function test_city_suggestions(): void
    {
        $this->sync()->run('areas');
        $this->sync()->run('cities');
        $this->sync()->run('warehouses');
        $repo = new \Ocs\Novaposhta\Repository($this->db, 'oc_');

        $uk = $repo->cities('Киї', 'uk');
        $this->assertSame(['Київ', 'Київець'], array_column($uk, 'name'), 'the city with branches is first');
        $this->assertSame('Київська', $uk[0]['area']);
        $this->assertSame('Киев', $repo->cities('кие', 'ru')[0]['name'], 'Russian names and prefix');
        $this->assertSame([], $repo->cities('к', 'uk'), 'one letter is too short');
        $this->assertSame([], $repo->cities("%' OR 1=1 --", 'uk'));
        $this->assertSame([], $repo->cities('Мі_то', 'uk'), '_ is not a wildcard');
    }

    // TEST-NP-006 - verifies REQ-NP-020 (branch search by number and street, type filter, exact number first, cargo hidden)
    public function test_point_search(): void
    {
        $this->sync()->run('areas');
        $this->sync()->run('cities');
        $this->sync()->run('warehouses');
        $repo = new \Ocs\Novaposhta\Repository($this->db, 'oc_');
        $city = 'cccccccc-0000-0000-0000-000000000001';

        $all = $repo->warehouses($city, '', 'all');
        $this->assertSame(4, $all['total'], 'cargo excluded');
        $this->assertSame([1, 5, 15, 34030], array_column($all['items'], 'number'));

        $this->assertSame([5, 15], array_column($repo->warehouses($city, '№5', 'all')['items'], 'number'), 'exact number first, "№" ignored');
        $this->assertSame([1, 15, 34030], array_column($repo->warehouses($city, 'хрещатик', 'all')['items'], 'number'));
        $this->assertSame([34030], array_column($repo->warehouses($city, 'хрещатик', 'postomat')['items'], 'number'));
        $this->assertSame([15], array_column($repo->warehouses($city, 'хрещ 50', 'all')['items'], 'number'), 'every word must match');
        $this->assertSame(0, $repo->warehouses('nope', '', 'all')['total']);
    }

    // TEST-NP-007 - verifies REQ-NP-021, REQ-NP-032 (a point is accepted only for its own city, only while active, never cargo)
    public function test_point_validation(): void
    {
        $this->sync()->run('areas');
        $this->sync()->run('cities');
        $this->sync()->run('warehouses');
        $repo = new \Ocs\Novaposhta\Repository($this->db, 'oc_');
        $city = 'cccccccc-0000-0000-0000-000000000001';
        $wh = 'wwwwwwww-0000-0000-0000-000000000005';

        $p = $repo->point($city, $wh);
        $this->assertSame('Київ', $p['city_uk']);
        $this->assertSame('Київська', $p['area_uk']);
        $this->assertSame(5, $p['number']);
        $this->assertSame(null, $repo->point('cccccccc-0000-0000-0000-000000000002', $wh), 'another city');
        $this->assertSame(null, $repo->point($city, 'wwwwwwww-0000-0000-0000-000000000007'), 'cargo');

        array_splice($this->data['warehouses'], 1, 1); // point 5 closes
        $this->sync()->run('warehouses');
        $this->assertSame(null, $repo->point($city, $wh), 'deactivated since');
    }

    // TEST-NP-012 - verifies REQ-NP-012 (area to zone matching, best effort)
    public function test_zone_matching(): void
    {
        $zones = [['zone_id' => 10, 'name' => 'Київ'], ['zone_id' => 11, 'name' => 'Київська область'], ['zone_id' => 12, 'name' => 'Львівська область']];
        $this->assertSame(11, \Ocs\Novaposhta\Repository::matchZone('Київська', $zones));
        $this->assertSame(12, \Ocs\Novaposhta\Repository::matchZone('Львівська', $zones));
        $this->assertSame(0, \Ocs\Novaposhta\Repository::matchZone('Невідома', $zones));
        $this->assertSame(0, \Ocs\Novaposhta\Repository::matchZone('', $zones));
    }

    // TEST-NP-003 - verifies REQ-NP-008, REQ-NP-052 (retries on 5xx, API errors never echo the key, empty key refused)
    public function test_client_retries_and_hides_key(): void
    {
        $n = 0;
        $client = new \Ocs\Novaposhta\Client('K-123', function () use (&$n) {
            return ++$n < 3 ? [503, ''] : [200, json_encode(['success' => true, 'data' => [['Ref' => 'x']]])];
        }, 2, function (): void {
        });
        $this->assertCount(1, $client->page('areas', 1, 500));
        $this->assertSame(3, $n);

        $n = 0;
        $down = new \Ocs\Novaposhta\Client('K-123', function () use (&$n) {
            $n++;
            return [500, ''];
        }, 2, function (): void {
        });
        try {
            $down->page('cities', 1, 10);
            $this->fail('expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertSame(3, $n, 'initial try + 2 retries');
            $this->assertTrue(strpos($e->getMessage(), 'K-123') === false);
        }

        $bad = new \Ocs\Novaposhta\Client('K-123', function () {
            return [200, json_encode(['success' => false, 'errors' => ['Wrong key K-123']])];
        }, 0, function (): void {
        });
        try {
            $bad->page('cities', 1, 10);
            $this->fail('expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'K-123') === false);
        }

        try {
            (new \Ocs\Novaposhta\Client(''))->page('cities', 1, 10);
            $this->fail('expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertContains('key', $e->getMessage());
        }
    }

    // TEST-NP-004 - verifies REQ-NP-020 (type classification of raw records)
    public function test_type_classification(): void
    {
        $this->assertSame('postomat', \Ocs\Novaposhta\Text::type(['CategoryOfWarehouse' => 'Postomat', 'Description' => 'x']));
        $this->assertSame('postomat', \Ocs\Novaposhta\Text::type(['Description' => 'Поштомат "Нова пошта" №1']));
        $this->assertSame('cargo', \Ocs\Novaposhta\Text::type(['CategoryOfWarehouse' => 'Branch', 'Description' => 'Вантажне відділення №3']));
        $this->assertSame('branch', \Ocs\Novaposhta\Text::type(['CategoryOfWarehouse' => 'Branch', 'Description' => 'Відділення №3']));
    }

    // TEST-NP-010 - verifies REQ-NP-040, REQ-NP-041, REQ-NP-042 (presets; custom selectors are parsed and stripped of markup)
    public function test_presets_and_custom_selectors(): void
    {
        $default = \Ocs\Novaposhta\Presets::groups('default', null);
        $this->assertSame('#input-shipping-city', $default[0]['city']);
        $simple = \Ocs\Novaposhta\Presets::groups('simple', null);
        $this->assertTrue(strpos($simple[0]['city'], '#shipping_address_city') === 0);

        $groups = \Ocs\Novaposhta\Presets::parse("#c | #a | #z | #co\nbroken\n.x<script> | .y");
        $this->assertCount(2, $groups);
        $custom = \Ocs\Novaposhta\Presets::groups('custom', $groups);
        $this->assertTrue(strpos($custom[1]['city'], '<') === false, 'markup is removed');
        $this->assertSame([], \Ocs\Novaposhta\Presets::groups('custom', null));
        $this->assertSame("#c | #a | #z | #co\n.x<script> | .y |  | ", \Ocs\Novaposhta\Presets::format($groups));
    }
}
