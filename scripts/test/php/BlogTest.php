<?php
declare(strict_types=1);

namespace Ocs\Test;

require_once dirname(__DIR__, 3) . '/custom/extensions/ocs_blog/upload/system/library/ocs/blog.php';
require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/structureddata.php';

final class BlogTest extends TestCase
{
    // TEST-BLG-002 - verifies REQ-BLG-002 (only known networks with http(s) links survive)
    public function test_social_links_are_cleaned(): void
    {
        $clean = \Ocs\Blog::cleanSocial(['facebook' => ' https://fb.com/a ', 'x' => 'javascript:alert(1)', 'telegram' => 'ftp://t.me/a', 'evil' => 'https://e.com', 'website' => 'http://site.ua']);
        $this->assertSame(['facebook' => 'https://fb.com/a', 'website' => 'http://site.ua'], $clean);
        $this->assertSame([], \Ocs\Blog::cleanSocial('nonsense'));
        $this->assertSame(['youtube' => 'https://youtube.com/c'], \Ocs\Blog::decodeSocial('{"youtube":"https://youtube.com/c","x":"bad"}'));
    }

    // TEST-BLG-005 - verifies REQ-BLG-005 (Person node: name required, text trimmed, sameAs from links)
    public function test_person_node(): void
    {
        $this->assertSame(null, \Ocs\Blog::person(['name' => '  ']));

        $p = \Ocs\Blog::person(['name' => 'Anna', 'position' => 'Editor', 'description' => '<b>Hi</b> there', 'image_url' => 'https://x.ua/a.jpg', 'social' => ['facebook' => 'https://fb.com/a']]);
        $this->assertSame('Person', $p['@type']);
        $this->assertSame('Editor', $p['jobTitle']);
        $this->assertSame('Hi there', $p['description']);
        $this->assertSame(['https://fb.com/a'], $p['sameAs']);
    }

    // TEST-BLG-005 - verifies REQ-BLG-005 (the BlogPosting author becomes the Person; the organisation stays the publisher)
    public function test_article_node_uses_person_as_author(): void
    {
        $org = ['name' => 'Shop', 'url' => 'https://shop.ua/'];
        $node = \Ocs\StructuredData::article([
            'url' => 'https://shop.ua/blog/a', 'headline' => 'Title', 'published' => '2026-10-01', 'publisher' => $org,
            'person' => ['name' => 'Anna', 'jobTitle' => 'Editor', 'sameAs' => ['https://fb.com/a', 'javascript:x']],
        ]);
        $this->assertSame('Person', $node['author']['@type']);
        $this->assertSame('Anna', $node['author']['name']);
        $this->assertSame(['https://fb.com/a'], $node['author']['sameAs']);
        $this->assertSame('2026-10-01', substr($node['datePublished'], 0, 10));

        $plain = \Ocs\StructuredData::article(['url' => 'https://shop.ua/blog/a', 'headline' => 'Title', 'published' => '2026-10-01', 'publisher' => $org]);
        $this->assertTrue(!isset($plain['author']) || $plain['author']['@type'] !== 'Person');
    }

    // TEST-BLG-011 - verifies REQ-BLG-011 (empty or zero date falls back to date_added)
    public function test_article_date_fallback(): void
    {
        $this->assertSame('2026-05-01', \Ocs\Blog::articleDate('2026-05-01', '2026-01-01 10:00:00'));
        $this->assertSame('2026-01-01 10:00:00', \Ocs\Blog::articleDate('0000-00-00', '2026-01-01 10:00:00'));
        $this->assertSame('2026-01-01 10:00:00', \Ocs\Blog::articleDate('', '2026-01-01 10:00:00'));
    }

    // TEST-BLG-006 - verifies REQ-BLG-033 (voter key: customer wins, guests hashed; click toggles or replaces)
    public function test_voting_rules(): void
    {
        $this->assertSame('c7', \Ocs\Blog::voterKey(7, '1.1.1.1', 'UA'));
        $a = \Ocs\Blog::voterKey(0, '1.1.1.1', 'UA', 'salt');
        $this->assertSame($a, \Ocs\Blog::voterKey(0, '1.1.1.1', 'UA', 'salt'));
        $this->assertTrue($a !== \Ocs\Blog::voterKey(0, '1.1.1.2', 'UA', 'salt'));
        $this->assertTrue(strlen($a) <= 32);

        $this->assertSame(1, \Ocs\Blog::nextVote(0, 1));
        $this->assertSame(0, \Ocs\Blog::nextVote(1, 1));
        $this->assertSame(-1, \Ocs\Blog::nextVote(1, -1));
        $this->assertSame(0, \Ocs\Blog::nextVote(-1, -1));
        $this->assertSame(1, \Ocs\Blog::nextVote(-1, 5));
    }

    // TEST-BLG-004 - verifies REQ-BLG-021 (language, then default language, then the old single keys)
    public function test_language_settings_fallbacks(): void
    {
        $json = json_encode([1 => ['name' => 'Blog', 'html_h1' => 'H1', 'meta_title' => '', 'meta_description' => '', 'meta_keyword' => ''], 2 => ['name' => 'Блог', 'html_h1' => '', 'meta_title' => '', 'meta_description' => '', 'meta_keyword' => '']]);
        $legacy = ['name' => 'Old', 'html_h1' => 'Old H1', 'meta_title' => 'Old T', 'meta_description' => '', 'meta_keyword' => ''];

        $ru = \Ocs\Blog::langSettings($json, 2, 1, $legacy);
        $this->assertSame('Блог', $ru['name']);
        $this->assertSame('H1', $ru['html_h1']);
        $this->assertSame('Old T', $ru['meta_title']);

        $this->assertSame('Old', \Ocs\Blog::langSettings('', 2, 1, $legacy)['name']);

        $posted = \Ocs\Blog::cleanLangSettings([1 => ['name' => ' A ', 'html_h1' => ['x']], 9 => ['name' => 'ghost']], [1, 2]);
        $this->assertSame('A', $posted[1]['name']);
        $this->assertSame('', $posted[1]['html_h1']);
        $this->assertSame('', $posted[2]['name']);
        $this->assertTrue(!isset($posted[9]));
    }
}
