<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once __DIR__ . '/Support/EventStubs.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/structureddata.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/contacts.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/catalog/controller/extension/ocs/events.php';

final class StructuredDataEventTest extends TestCase
{
    private $registry;
    private $event;

    protected function setUp(): void
    {
        if (!defined('DIR_IMAGE')) {
            define('DIR_IMAGE', sys_get_temp_dir() . '/ocs-no-images/');
        }
        if (!defined('DB_PREFIX')) {
            define('DB_PREFIX', 'oc_');
        }

        foreach (['ldDone' => false, 'ldLists' => []] as $name => $value) {
            $p = new \ReflectionProperty(\ControllerExtensionOcsEvents::class, $name);
            $p->setAccessible(true);
            $p->setValue(null, $value);
        }

        $r = new \Registry();
        $r->set('config', new \StubBag([
            'config_theme' => 'ocs', 'config_name' => 'Shop & Co', 'config_language' => 'ru-ru', 'config_store_id' => 0, 'config_review_status' => 1,
            'config_customer_price' => 0, 'config_tax' => 0, 'config_customer_group_id' => 1, 'config_ssl' => 'https://shop.test/', 'config_url' => 'http://shop.test/',
            'theme_ocs_contact' => ['ru-ru' => ['address' => 'Kyiv, Main 1', 'hours' => '', 'phones' => '+380 44 123 45 67']],
            'theme_ocs_contact_links' => [['icon' => 'instagram', 'kind' => 'social', 'title' => ['ru-ru' => 'Insta'], 'url' => 'https://instagram.com/shop', 'sort' => 1]],
        ]));
        $r->set('url', new \StubUrl());
        $r->set('document', new \StubModel(['getLinks' => []]));
        $r->set('request', (object)['server' => ['REQUEST_METHOD' => 'GET', 'HTTPS' => 'on', 'REQUEST_URI' => '/category/phones'], 'get' => []]);
        $r->set('session', (object)['data' => ['currency' => 'UAH']]);
        $r->set('customer', new \StubModel(['isLogged' => false]));
        $r->set('tax', new \StubModel(['calculate' => function ($v) {
            return $v;
        }]));
        $r->set('currency', new \StubModel(['format' => function ($n, $c, $v = '', $f = true) {
            return round($n * 40, 2);
        }]));
        $r->set('db', new \StubDb());
        $r->set('log', new \StubLog());

        $load = new \StubLoad();
        $load->registry = $r;
        $load->models = [
            'setting/setting' => new \StubModel(['getSettingValue' => 'ru-ru']),
            'catalog/product' => new \StubModel(['getProduct' => function ($id) {
                return ['product_id' => $id, 'name' => 'Mac', 'model' => 'M1', 'sku' => '', 'mpn' => '', 'ean' => '4006381333931', 'upc' => '', 'jan' => '', 'manufacturer' => 'Apple',
                    'price' => 100.0, 'special' => 80.0, 'tax_class_id' => 0, 'quantity' => 5, 'rating' => '4.5', 'reviews' => '2'];
            }]),
            'catalog/review' => new \StubModel(['getReviewsByProductId' => function () {
                return [['author' => 'Ivan', 'rating' => 5, 'date_added' => '2026-03-04 05:06:07', 'text' => 'Great']];
            }]),
        ];
        $r->set('load', $load);
        $r->get('db')->row = ['date_end' => '2026-12-31'];

        $this->registry = $r;
        $this->event = new \ControllerExtensionOcsEvents($r);
    }

    private function page(string $route, array $data, string $html = '<html><head><title>x</title></head><body>b</body></html>'): string
    {
        $this->event->structuredData($route, $data, $html);

        return $html;
    }

    /** @return array[] nodes of the single script grouped by type */
    private function graph(string $html): array
    {
        $this->assertSame(1, substr_count($html, 'application/ld+json'), 'exactly one script');
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $doc = json_decode($m[1], true);
        $this->assertTrue(is_array($doc), 'valid JSON');
        $ids = array_column($doc['@graph'], '@id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'unique @id');
        $this->assertTrue(strpos($html, '</script>') < strpos($html, '</head>'), 'inside head');

        $byType = [];
        foreach ($doc['@graph'] as $node) {
            $byType[$node['@type']][] = $node;
        }

        return $byType;
    }

    // TEST-SD-006 - verifies REQ-SD-001, REQ-SD-004, REQ-SD-005, REQ-SD-008 (product page graph)
    public function test_product_page(): void
    {
        $this->registry->set('document', new \StubModel(['getLinks' => [['href' => 'http://shop.test/index.php?route=product/product&amp;product_id=7', 'rel' => 'canonical']]]));

        $g = $this->graph($this->page('product/product', [
            'product_id' => 7, 'heading_title' => 'Mac </script>', 'description' => '&lt;p&gt;Fast&lt;/p&gt;', 'popup' => 'https://shop.test/image/a.jpg',
            'images' => [['popup' => 'https://shop.test/image/b.jpg']],
            'breadcrumbs' => [['text' => 'Home', 'href' => 'http://shop.test/'], ['text' => 'Mac', 'href' => 'http://shop.test/mac']],
            'ocs_px' => ['accessories' => [['href' => 'http://shop.test/index.php?route=product/product&amp;product_id=8']], 'text_accessories' => 'Accessories', 'similar' => []],
        ]));

        $this->assertSame(['Organization', 'WebSite', 'Product', 'ItemList', 'WebPage', 'BreadcrumbList'], array_keys($g));

        $p = $g['Product'][0];
        $this->assertSame('3200.00', $p['offers']['price'], 'special price in the session currency');
        $this->assertSame('UAH', $p['offers']['priceCurrency']);
        $this->assertSame('2026-12-31', $p['offers']['priceValidUntil']);
        $this->assertSame('https://schema.org/InStock', $p['offers']['availability']);
        $this->assertSame('4006381333931', $p['gtin']);
        $this->assertSame('M1', $p['sku']);
        $this->assertSame(4.5, $p['aggregateRating']['ratingValue']);
        $this->assertSame('Ivan', $p['review'][0]['author']['name']);
        $this->assertSame('Mac', $p['name'], 'tags stripped');
        $this->assertSame($p['@id'], $g['WebPage'][0]['mainEntity']['@id']);
        $this->assertTrue(strpos($p['url'], 'https://') === 0, 'store scheme');
        $this->assertSame(['https://instagram.com/shop'], $g['Organization'][0]['sameAs']);
        $this->assertSame('+380441234567', $g['Organization'][0]['contactPoint'][0]['telephone']);
        $this->assertSame('ru-RU', $g['WebPage'][0]['inLanguage']);
        $this->assertSame(2, count($g['BreadcrumbList'][0]['itemListElement']));
    }

    // TEST-SD-006 - verifies REQ-SD-006 (listing positions continue across pages; carousels of the home page)
    public function test_listing_and_home(): void
    {
        $this->registry->get('request')->get['page'] = '3';

        $g = $this->graph($this->page('product/category', [
            'heading_title' => 'Phones', 'limit' => 2, 'breadcrumbs' => [['text' => 'Home', 'href' => 'http://shop.test/'], ['text' => 'Phones', 'href' => '']],
            'products' => [['href' => 'http://shop.test/p1'], ['href' => 'http://shop.test/p2']],
        ]));
        $this->assertTrue(isset($g['CollectionPage']), 'category is a collection page');
        $this->assertSame([5, 6], array_column($g['ItemList'][0]['itemListElement'], 'position'));
        $this->assertSame($g['ItemList'][0]['@id'], $g['CollectionPage'][0]['mainEntity']['@id']);

        $this->setUp();
        $module = ['products' => [['href' => 'http://shop.test/p1']], 'heading_title' => 'Featured'];
        $html = '<div>module</div>';
        $route = 'extension/module/featured';
        $this->event->structuredData($route, $module, $html);
        $this->assertSame('<div>module</div>', $html, 'modules are not touched');

        $g = $this->graph($this->page('common/home', ['breadcrumbs' => []]));
        $this->assertSame('Featured', $g['ItemList'][0]['name']);
        $this->assertTrue(!isset($g['BreadcrumbList']), 'no breadcrumbs on the home page');
    }

    // TEST-SD-006 - verifies REQ-SD-001, REQ-SD-011 (partials, header, POST, error pages, switched off, second head: untouched)
    public function test_guards(): void
    {
        $partial = '<div>no head</div>';
        $this->assertSame($partial, $this->page('product/category', [], $partial));

        $header = '<html><head></head></html>';
        $this->assertSame($header, $this->page('common/header', ['breadcrumbs' => []], $header));
        $this->assertSame($header, $this->page('error/not_found', ['breadcrumbs' => []], $header));

        $this->registry->get('request')->server['REQUEST_METHOD'] = 'POST';
        $this->assertSame($header, $this->page('information/contact', [], $header));
        $this->registry->get('request')->server['REQUEST_METHOD'] = 'GET';

        $this->registry->get('config')->values['theme_ocs_schema_status'] = 0;
        $this->assertSame($header, $this->page('information/contact', [], $header));
        $this->registry->get('config')->values['theme_ocs_schema_status'] = 1;

        $this->registry->get('config')->values['config_theme'] = 'default';
        $this->assertSame($header, $this->page('information/contact', [], $header));
        $this->registry->get('config')->values['config_theme'] = 'ocs';

        $first = $this->page('information/contact', ['heading_title' => 'Contacts', 'breadcrumbs' => []]);
        $g = $this->graph($first);
        $this->assertTrue(isset($g['ContactPage']), 'contact page type');
        $this->assertSame($first, $this->page('information/contact', [], $first), 'the second call does not add a second script');
        $this->assertSame(0, count($this->registry->get('log')->lines), 'nothing logged: ' . implode('|', $this->registry->get('log')->lines));
    }
}
