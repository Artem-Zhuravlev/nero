<?php
declare(strict_types=1);

namespace Ocs\Test;

use Ocs\StructuredData as SD;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/structureddata.php';

final class StructuredDataTest extends TestCase
{
    protected function setUp(): void
    {
        SD::$base = 'https://shop.test';
        SD::$host = 'shop.test';
        SD::$scheme = '';
    }

    private function org(): array
    {
        return ['id' => 'https://shop.test/#organization', 'name' => 'Shop & Co', 'url' => 'https://shop.test/'];
    }

    private function decode(string $json): array
    {
        $data = json_decode($json, true);
        $this->assertTrue(is_array($data) && $data['@context'] === 'https://schema.org' && is_array($data['@graph']), 'valid JSON-LD document');

        return $data['@graph'];
    }

    // TEST-SD-001 - verifies REQ-SD-010 (hostile input cannot leave the script element and still gives valid JSON)
    public function test_hostile_strings_are_safe(): void
    {
        $evil = "</script><script>alert(1)</script> \"q\" 'a' &amp; <!-- x --> \xB1\x31 " . str_repeat('я', 6000);
        $product = SD::product([
            'name' => $evil, 'url' => 'https://shop.test/p?a=1&amp;b=2', 'description' => '&lt;p&gt;Fast &amp;amp; light&lt;/p&gt;' . $evil,
            'price' => 10, 'currency' => 'UAH', 'in_stock' => true, 'brand' => '<b>Apple</b>', 'images' => ['javascript:alert(1)', 'https://cdn.test/a.jpg', '/image/b.jpg'],
        ]);
        $json = SD::graph([$product, SD::breadcrumbs('https://shop.test/p#bc', [['text' => 'Home', 'href' => 'https://shop.test/'], ['text' => $evil, 'href' => '']])]);

        foreach (['<', '>', '&', "'", '</'] as $bad) {
            $this->assertTrue(strpos($json, $bad) === false, 'no raw ' . $bad);
        }

        $graph = $this->decode($json);

        $this->assertSame('Product', $graph[0]['@type']);
        $this->assertSame('https://shop.test/p?a=1&b=2', $graph[0]['url'], 'entities decoded once');
        $this->assertSame('Apple', $graph[0]['brand']['name']);
        $this->assertSame(['https://cdn.test/a.jpg', 'https://shop.test/image/b.jpg'], $graph[0]['image'], 'javascript: dropped, relative made absolute');
        $this->assertTrue(strpos($graph[0]['description'], 'Fast & light') === 0, 'stored-escaped HTML becomes plain text');
        $this->assertTrue(mb_strlen($graph[0]['description'], 'UTF-8') <= 5000 && mb_strlen($graph[0]['name'], 'UTF-8') <= 300, 'truncated on characters');
        $this->assertSame(2, count($graph[1]['itemListElement']), 'home and the current page');
        $this->assertTrue(strpos($graph[1]['itemListElement'][1]['name'], 'alert(1)') === 0, 'tags are stripped from names');
    }

    // TEST-SD-002 - verifies REQ-SD-010 (URL, price, date, currency, language, gtin rules)
    public function test_sanitisers(): void
    {
        $this->assertSame('', SD::url('http://evil.test/x', true), 'foreign host is not an own URL');
        $this->assertSame('http://www.shop.test/x', SD::url('http://www.shop.test/x', true), 'www is the same host');
        $this->assertSame('', SD::url('ftp://shop.test/x'));
        $this->assertSame('', SD::url('https://shop.test/a b'));
        $this->assertSame('', SD::url('//evil.test/x'));
        SD::$scheme = 'https';
        $this->assertSame('https://shop.test/x?a=1', SD::url('http://shop.test/x?a=1', true), 'own URLs get the store scheme');
        $this->assertSame('http://cdn.test/x.jpg', SD::url('http://cdn.test/x.jpg'), 'foreign hosts keep theirs');
        SD::$scheme = '';
        $this->assertSame('99.50', SD::price('99.5'));
        $this->assertSame('', SD::price(0));
        $this->assertSame('', SD::price('abc'));
        $this->assertSame('UAH', SD::currency(' uah '));
        $this->assertSame('', SD::currency('GRN1'));
        $this->assertSame('2026-12-31', SD::date('2026-12-31 00:00:00', true));
        $this->assertSame('', SD::date('0000-00-00'));
        $this->assertSame('', SD::date('not a date'));
        $this->assertSame('ru-RU', SD::lang('ru-ru'));
        $this->assertSame('en', SD::lang('EN'));
        $this->assertSame('', SD::lang('<x>'));
        $this->assertSame('4006381333931', SD::gtin(['abc', '12345', '4006381333931']));
        $this->assertSame('', SD::gtin(['123456789']));
        $this->assertSame('+380441234567', SD::phone('tel:+380441234567'));
        $this->assertSame('', SD::phone('12'));
        $this->assertSame(4.5, SD::rating('4.46'));
        $this->assertTrue(SD::rating(0) === null && SD::rating(6) === null);
    }

    // TEST-SD-003 - verifies REQ-SD-002, REQ-SD-003, REQ-SD-006, REQ-SD-008 (organization, breadcrumbs, lists, page node, unique @id)
    public function test_site_nodes(): void
    {
        $org = SD::organization($this->org() + [
            'logo' => 'https://shop.test/image/logo.png', 'same_as' => ['https://t.me/shop', 'https://t.me/shop', 'javascript:x', 'https://instagram.com/shop'],
            'phones' => ['tel:+380441234567', '1'], 'address' => 'Kyiv, <b>Main</b> st. 1',
        ]);
        $this->assertSame('Shop & Co', $org['name']);
        $this->assertSame(['https://t.me/shop', 'https://instagram.com/shop'], $org['sameAs']);
        $this->assertSame('customer service', $org['contactPoint'][0]['contactType']);
        $this->assertSame(1, count($org['contactPoint']));
        $this->assertSame('Kyiv, Main st. 1', $org['address']['streetAddress']);
        $this->assertTrue(SD::organization(['name' => '', 'url' => 'https://shop.test/', 'id' => 'https://shop.test/#o']) === null);

        $bc = SD::breadcrumbs('https://shop.test/c#bc', [['text' => 'Home', 'href' => 'https://shop.test/'], ['text' => 'A &amp; B', 'href' => 'https://shop.test/c?x=1&amp;y=2'], ['text' => 'Now', 'href' => 'https://shop.test/now']]);
        $this->assertSame([1, 2, 3], array_column($bc['itemListElement'], 'position'));
        $this->assertSame('A & B', $bc['itemListElement'][1]['name']);
        $this->assertSame('https://shop.test/c?x=1&y=2', $bc['itemListElement'][1]['item']);
        $this->assertTrue(!isset($bc['itemListElement'][2]['item']), 'the current page has no item');
        $single = SD::breadcrumbs('https://shop.test/x#bc', [['text' => 'Only', 'href' => '']]);
        $this->assertSame(1, count($single['itemListElement']));
        $this->assertTrue(SD::breadcrumbs('https://shop.test/x#bc', []) === null);

        $items = [];
        for ($i = 1; $i <= 60; $i++) {
            $items[] = ['href' => 'https://shop.test/p' . $i];
        }
        $items[] = ['href' => 'https://evil.test/p'];
        $list = SD::itemList('https://shop.test/c#list', 'Phones', $items, 24);
        $this->assertSame(50, count($list['itemListElement']), 'at most 50');
        $this->assertSame(25, $list['itemListElement'][0]['position'], 'positions continue across pages');
        $this->assertTrue(SD::itemList('https://shop.test/c#list', 'x', [['href' => '']]) === null, 'empty list is dropped');

        $page = SD::page(['type' => 'SearchResultsPage', 'url' => 'https://shop.test/s', 'name' => 'Search', 'lang' => 'uk-ua', 'website' => 'https://shop.test/#website', 'breadcrumb' => 'https://shop.test/s#bc']);
        $this->assertSame('https://shop.test/s#webpage', $page['@id']);
        $this->assertSame('uk-UA', $page['inLanguage']);
        $this->assertSame('https://shop.test/#website', $page['isPartOf']['@id']);
        $this->assertSame('WebPage', SD::page(['type' => 'Evil', 'url' => 'https://shop.test/s', 'name' => 'x'])['@type'], 'type is whitelisted');

        $json = SD::graph([$org, $org, null, ['x' => 1], $bc]);
        $ids = array_column($this->decode($json), '@id');
        $this->assertSame(array_values(array_unique($ids)), $ids, 'no duplicate @id');
        $this->assertSame(2, count($ids));
        $this->assertSame('', SD::graph([null]));
    }

    // TEST-SD-004 - verifies REQ-SD-004, REQ-SD-007 (product / offer / article required properties)
    public function test_product_and_article(): void
    {
        $seller = $this->org();
        $p = SD::product([
            'name' => 'Mac', 'url' => 'https://shop.test/mac', 'sku' => 'M1', 'mpn' => 'MPN1', 'gtin' => ['', '4006381333931'], 'price' => 99.5,
            'currency' => 'UAH', 'in_stock' => false, 'valid_until' => '2026-12-31', 'seller' => $seller,
        ]);
        $o = $p['offers'];
        $this->assertSame('99.50', $o['price']);
        $this->assertSame('UAH', $o['priceCurrency']);
        $this->assertSame('https://schema.org/OutOfStock', $o['availability']);
        $this->assertSame('https://schema.org/NewCondition', $o['itemCondition']);
        $this->assertSame('2026-12-31', $o['priceValidUntil']);
        $this->assertSame('Shop & Co', $o['seller']['name']);
        $this->assertSame('4006381333931', $p['gtin']);
        $this->assertSame('MPN1', $p['mpn']);

        $in = SD::product(['name' => 'Mac', 'url' => 'https://shop.test/mac', 'price' => 1, 'currency' => 'UAH', 'in_stock' => true]);
        $this->assertSame('https://schema.org/InStock', $in['offers']['availability']);
        $this->assertTrue(!isset($in['offers']['priceValidUntil']) && !isset($in['gtin']) && !isset($in['aggregateRating']));

        $this->assertTrue(SD::product(['name' => 'Mac', 'url' => 'https://shop.test/mac', 'price' => false, 'currency' => 'UAH']) === null, 'hidden price and no reviews: node dropped');
        $this->assertTrue(SD::product(['name' => '', 'url' => 'https://shop.test/mac', 'price' => 5, 'currency' => 'UAH']) === null);
        $this->assertTrue(SD::product(['name' => 'Mac', 'url' => 'https://other.test/mac', 'price' => 5, 'currency' => 'UAH']) === null, 'foreign URL');
        $this->assertTrue(SD::product(['name' => 'Mac', 'url' => 'https://shop.test/mac', 'price' => 5, 'currency' => '']) === null, 'no valid currency: no offer, nothing else');

        $a = SD::article(['url' => 'https://shop.test/blog/a', 'headline' => str_repeat('x', 200), 'image' => 'https://shop.test/image/a.jpg', 'published' => '2026-01-02 10:00:00', 'modified' => '0000-00-00 00:00:00', 'publisher' => $seller]);
        $this->assertSame('BlogPosting', $a['@type']);
        $this->assertSame(110, strlen($a['headline']));
        $this->assertSame($a['datePublished'], $a['dateModified'], 'missing modified date falls back to published');
        $this->assertSame('Shop & Co', $a['publisher']['name']);
        $this->assertTrue(SD::article(['url' => 'https://shop.test/blog/a', 'headline' => 'x', 'published' => '']) === null, 'no date: dropped');
    }

    // TEST-SD-005 - verifies REQ-SD-005 (rating maths and reviews are never invented)
    public function test_reviews(): void
    {
        $base = ['name' => 'Mac', 'url' => 'https://shop.test/mac', 'price' => 5, 'currency' => 'UAH'];

        $none = SD::product($base + ['rating' => 5, 'review_total' => 0, 'reviews' => []]);
        $this->assertTrue(!isset($none['aggregateRating']) && !isset($none['review']), 'no reviews, no rating');

        $reviews = [['author' => 'Ivan </b>', 'rating' => 5, 'date' => '2026-03-04 05:06:07', 'text' => 'Great &amp; fast'], ['author' => '', 'rating' => 4], ['author' => 'X', 'rating' => 9]];
        for ($i = 0; $i < 12; $i++) {
            $reviews[] = ['author' => 'U' . $i, 'rating' => 3];
        }
        $p = SD::product($base + ['rating' => '4.46', 'review_total' => 14, 'reviews' => $reviews]);

        $this->assertSame(4.5, $p['aggregateRating']['ratingValue']);
        $this->assertSame(14, $p['aggregateRating']['reviewCount']);
        $this->assertSame(5, $p['aggregateRating']['bestRating']);
        $this->assertSame(10, count($p['review']), 'at most 10, invalid ones skipped');
        $this->assertSame('Great & fast', $p['review'][0]['reviewBody']);
        $this->assertSame('Ivan', $p['review'][0]['author']['name']);
        $this->assertSame(5, $p['review'][0]['reviewRating']['ratingValue']);

        $only = SD::product(['name' => 'Mac', 'url' => 'https://shop.test/mac', 'price' => false, 'currency' => 'UAH', 'reviews' => [['author' => 'A', 'rating' => 4]]]);
        $this->assertTrue($only !== null && !isset($only['offers']) && count($only['review']) === 1, 'reviews alone keep the product');
    }

    public function test_inject_before_head_only(): void
    {
        $html = '<html><head><title>x</title></head><body></body></html>';
        $out = SD::inject($html, '{"a":1}');
        $this->assertTrue(strpos($out, '<script type="application/ld+json">{"a":1}</script>') < strpos($out, '</head>'));
        $this->assertSame('<p>partial</p>', SD::inject('<p>partial</p>', '{"a":1}'));
        $this->assertSame($html, SD::inject($html, ''));
    }
}
