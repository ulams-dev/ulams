<?php

namespace Ulams\Settings\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Ulams\Settings\Support\ThemePresets;
use Ulams\Settings\Tests\TestCase;

/** The API's preset list is the generated manifest, the same file `front/ui/catalogue/themes.json` holds. */
class ThemePresetsTest extends TestCase
{
    #[Test]
    public function testTheApiCopyIsTheCatalogueManifest(): void
    {
        $api = __DIR__ . '/../../resources/themes.json';
        $catalogue = __DIR__ . '/../../../../../front/ui/catalogue/themes.json';
        if (!is_file($catalogue)) {
            $this->markTestSkipped('front/ui is not part of this checkout.');
        }
        $this->assertSame(file_get_contents($catalogue), file_get_contents($api));
    }

    #[Test]
    public function testEveryPresetHasItsTokensAndAReadableBackground(): void
    {
        $this->assertSame(['coffee', 'oncall', 'nightsky', 'gravity', 'poland', 'ulam'], ThemePresets::names());
        foreach (ThemePresets::all() as $name => $preset) {
            foreach (['bg', 'card', 'text', 'muted', 'primary', 'onPrimary', 'accent', 'border', 'themeColor'] as $token) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $preset[$token], "$name.$token");
            }
            $this->assertSame($preset['bg'], ThemePresets::background($name));
        }
    }

    #[Test]
    public function testLookups(): void
    {
        $this->assertTrue(ThemePresets::has('poland'));
        $this->assertFalse(ThemePresets::has('platform'));
        $this->assertFalse(ThemePresets::has(['poland']));
        $this->assertNull(ThemePresets::background('nope'));
        $this->assertTrue(ThemePresets::isAccent('#c8102E'));
        foreach (['c8102e', '#fff', '#c8102e00', 'red', '', null, 12] as $bad) {
            $this->assertFalse(ThemePresets::isAccent($bad), var_export($bad, true));
        }
    }
}
