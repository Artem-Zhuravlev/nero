<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\Migrate\Context;
use Ocs\Test\Support\SqliteDb;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_product_extras/upload/system/library/ocs/productextras.php';

final class ProductExtrasTest extends TestCase
{
    private function groups(): array
    {
        return [
            ['attribute' => [
                ['attribute_id' => 1, 'name' => 'CPU', 'text' => 'M2'],
                ['attribute_id' => 2, 'name' => 'RAM', 'text' => '16 GB'],
                ['attribute_id' => 3, 'name' => 'Empty', 'text' => ' '],
            ]],
            ['attribute' => [
                ['attribute_id' => 4, 'name' => 'Disk', 'text' => '512 GB'],
                ['attribute_id' => 5, 'name' => 'Colour', 'text' => 'Grey'],
                ['attribute_id' => 6, 'name' => 'Weight', 'text' => '1 kg'],
            ]],
        ];
    }

    // TEST-PRD-002 - verifies REQ-PRD-002 (chosen attributes in the chosen order; default = the first filled ones; at most 4; none = hidden block)
    public function test_key_attributes(): void
    {
        $this->assertSame(['Disk', 'CPU'], array_column(\Ocs\ProductExtras::keyAttributes($this->groups(), [4, 1]), 'name'));
        $this->assertSame(['CPU', 'RAM', 'Disk', 'Colour'], array_column(\Ocs\ProductExtras::keyAttributes($this->groups(), []), 'name'));
        $this->assertSame([], \Ocs\ProductExtras::keyAttributes($this->groups(), [99]));
        $this->assertSame([], \Ocs\ProductExtras::keyAttributes([], []));
    }

    // TEST-PRD-004 - verifies REQ-PRD-021 (price band around the current price)
    public function test_price_band_and_ids(): void
    {
        $this->assertSame([70.0, 130.0], \Ocs\ProductExtras::priceBand(100));
        $this->assertSame([0.0, 0.0], \Ocs\ProductExtras::priceBand(-5));
        $this->assertSame([5, 7, 9], \Ocs\ProductExtras::ids(' 5, 7 ,x,5,0,-2,9'));
        $this->assertSame([], \Ocs\ProductExtras::ids(''));
        $this->assertCount(3, \Ocs\ProductExtras::ids('1,2,3,4,5', 3));
    }

    // TEST-PRD-003 - verifies REQ-PRD-010 (question validation: name, optional e-mail, length, no links)
    public function test_validate_question(): void
    {
        $ok = \Ocs\ProductExtras::validateQuestion(['name' => ' Ivan  P ', 'email' => 'A@B.com', 'text' => 'Is there a warranty?']);
        $this->assertSame([], $ok['errors']);
        $this->assertSame('Ivan P', $ok['clean']['name']);
        $this->assertSame('a@b.com', $ok['clean']['email']);

        $this->assertSame('name', \Ocs\ProductExtras::validateQuestion(['name' => 'I', 'text' => 'Is there a warranty?'])['errors']['name']);
        $this->assertSame('email', \Ocs\ProductExtras::validateQuestion(['name' => 'Ivan', 'email' => 'nope', 'text' => 'Is there a warranty?'])['errors']['email']);
        $this->assertSame('text', \Ocs\ProductExtras::validateQuestion(['name' => 'Ivan', 'text' => 'Hi'])['errors']['text']);
        $this->assertSame('text', \Ocs\ProductExtras::validateQuestion(['name' => 'Ivan', 'text' => str_repeat('a', 1001)])['errors']['text']);
        $this->assertSame('links', \Ocs\ProductExtras::validateQuestion(['name' => 'Ivan', 'text' => 'buy at www.spam.com now'])['errors']['text']);
        $this->assertSame([], \Ocs\ProductExtras::validateQuestion(['name' => 'Ivan', 'text' => 'Is there a warranty?'])['errors']);
    }

    // TEST-PRD-006 - verifies REQ-PRD-040 (JSON-LD: Product with offer and rating, BreadcrumbList; cannot close the script tag)
    public function test_schema(): void
    {
        $json = \Ocs\ProductExtras::schema(
            ['name' => 'Mac </script><b>', 'url' => 'http://x/p?a=1&b=2', 'price' => 99.5, 'currency' => 'UAH', 'in_stock' => false, 'rating' => 4, 'reviews' => 3, 'brand' => 'Apple', 'sku' => 'M1'],
            [['text' => 'Home', 'href' => 'http://x/'], ['text' => 'A &amp; B', 'href' => 'http://x/c?x=1&amp;y=2']]
        );

        $this->assertTrue(strpos($json, '</script>') === false, 'tags are escaped');

        $data = json_decode($json, true);

        $this->assertSame('Product', $data[0]['@type']);
        $this->assertSame('99.50', $data[0]['offers']['price']);
        $this->assertSame('https://schema.org/OutOfStock', $data[0]['offers']['availability']);
        $this->assertSame(3, $data[0]['aggregateRating']['reviewCount']);
        $this->assertSame('Apple', $data[0]['brand']['name']);
        $this->assertSame('BreadcrumbList', $data[1]['@type']);
        $this->assertSame('A & B', $data[1]['itemListElement'][1]['name']);
        $this->assertSame('http://x/c?x=1&y=2', $data[1]['itemListElement'][1]['item']);
        $this->assertSame(2, $data[1]['itemListElement'][1]['position']);

        $none = json_decode(\Ocs\ProductExtras::schema(['name' => 'N', 'url' => 'u', 'price' => false, 'rating' => 0, 'reviews' => 0], []), true);
        $this->assertTrue(!isset($none[0]['offers']) && !isset($none[0]['aggregateRating']), 'no price and no reviews = no offer and no rating');
    }

    // TEST-PRD-006 - verifies REQ-PRD-041, REQ-EXT-031 (install: tables, off by default, events, idempotent)
    public function test_install_migration(): void
    {
        $db = new SqliteDb();
        $db->createCoreSubset();
        $m = new Context($db, 'oc_', dirname(__DIR__, 3));
        $fn = require dirname(__DIR__, 3) . '/custom/extensions/ocs_product_extras/db/001_install.php';
        $fn($m);
        $fn($m);

        $this->assertTrue($db->tableExists('oc_ocs_question'));
        $this->assertTrue($db->tableExists('oc_ocs_product_accessory'));
        $this->assertTrue($m->extension->isRegistered('module', 'ocs_product_extras'));
        $this->assertSame('0', (string) $m->setting->get('module_ocs_product_extras', 'module_ocs_product_extras_status'));
        $this->assertSame('1', (string) $m->setting->get('module_ocs_product_extras', 'module_ocs_product_extras_questions'));
        $this->assertCount(5, $db->pdo->query("SELECT code FROM oc_event WHERE code LIKE 'ocs_product_extras_%'")->fetchAll());
    }

    // TEST-VIS-007 - verifies REQ-VIS-006 (review summary: total, average, one row per rating with its share)
    public function test_star_distribution(): void
    {
        $s = \Ocs\ProductExtras::stars([5 => 6, 4 => 3, 3 => 1, 2 => 0, 1 => 0]);
        $this->assertSame(10, $s['total']);
        $this->assertSame(4.5, $s['avg']);
        $this->assertSame([5, 4, 3, 2, 1], array_column($s['rows'], 'rating'));
        $this->assertSame([60, 30, 10, 0, 0], array_column($s['rows'], 'pct'));

        $none = \Ocs\ProductExtras::stars([]);
        $this->assertSame(0, $none['total']);
        $this->assertSame(0, $none['avg']);
        $this->assertSame([0, 0, 0, 0, 0], array_column($none['rows'], 'pct'));
    }
}
