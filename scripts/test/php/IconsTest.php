<?php
declare(strict_types=1);

namespace Ocs\Test;

// SPEC-042 REQ-ICN-003: stored icon names map to sprite symbols, unknown names fall back, nothing unsafe leaks into markup.
final class IconsTest extends TestCase
{
    // TEST-ICN-003 - verifies REQ-ICN-003
    public function test_names_map_to_symbols(): void
    {
        require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/icons.php';

        $this->assertSame('i-mobile', \Ocs\Icons::symbol('fa-mobile'));
        $this->assertSame('i-mobile', \Ocs\Icons::symbol('fa fa-mobile'));
        $this->assertSame('i-truck', \Ocs\Icons::symbol('truck'));
        $this->assertSame('i-return', \Ocs\Icons::symbol('undo'));
        $this->assertSame('i-cart', \Ocs\Icons::symbol('  FA-Shopping-Cart '));
        $this->assertSame('i-heart', \Ocs\Icons::symbol('i-heart'));
    }

    // TEST-ICN-003 - verifies REQ-ICN-003 (unknown or hostile names never produce an empty or unsafe symbol id)
    public function test_unknown_names_fall_back(): void
    {
        require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/icons.php';

        $this->assertSame('i-dot', \Ocs\Icons::symbol('fa-does-not-exist'));
        $this->assertSame('i-dot', \Ocs\Icons::symbol(''));
        $this->assertSame('i-dot', \Ocs\Icons::symbol('"><script>'));
        $this->assertSame('i-dot', \Ocs\Icons::symbol('i-"><x'));
    }

    // TEST-ICN-002 - verifies REQ-ICN-002 (every symbol Icons can return exists in the sprite)
    public function test_every_mapped_symbol_is_in_the_sprite(): void
    {
        require_once dirname(__DIR__, 3) . '/custom/extensions/ocs/upload/system/library/ocs/icons.php';

        $sprite = (string) file_get_contents(dirname(__DIR__, 3) . '/custom/theme/ocs/template/ocs/sprite.twig');

        foreach (\Ocs\Icons::symbols() as $symbol) {
            $this->assertTrue(strpos($sprite, 'id="' . $symbol . '"') !== false, "sprite has {$symbol}");
        }
    }
}
