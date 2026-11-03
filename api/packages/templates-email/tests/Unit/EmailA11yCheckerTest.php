<?php

namespace Ulams\TemplatesEmail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\TemplatesEmail\Accessibility\EmailA11yChecker;

class EmailA11yCheckerTest extends TestCase
{
    private function codes(string $html): array
    {
        return array_column((new EmailA11yChecker())->check($html), 'code');
    }

    private function doc(string $body, string $lang = 'en', string $title = 'Hello'): string
    {
        return '<!doctype html><html lang="' . $lang . '"><head><title>' . $title . '</title></head><body>' . $body . '</body></html>';
    }

    public function testACleanEmailHasNoWarnings(): void
    {
        $html = $this->doc('<table role="presentation"><tr><td style="background:#ffffff"><p style="color:#222222">Hi</p>'
            . '<img src="a.png" alt="Logo"><a href="https://x.test">Open</a></td></tr></table>');

        $this->assertSame([], (new EmailA11yChecker())->check($html));
    }

    public function testEmptyInputHasNoWarnings(): void
    {
        $this->assertSame([], (new EmailA11yChecker())->check('  '));
    }

    public function testImageWithoutAltIsReportedButEmptyAltIsNot(): void
    {
        $warnings = (new EmailA11yChecker())->check($this->doc('<img src="a.png"><img src="b.png"><img src="c.png" alt="">'));

        $this->assertSame(['image-missing-alt'], array_column($warnings, 'code'));
        $this->assertSame(2, $warnings[0]['count']);
    }

    public function testTrackingPixelIsIgnored(): void
    {
        $this->assertSame([], $this->codes($this->doc('<img src="p.gif" width="1" height="1">')));
    }

    public function testMissingOrUndefinedLanguage(): void
    {
        $this->assertContains('missing-lang', $this->codes('<html><head><title>t</title></head><body><p>x</p></body></html>'));
        $this->assertContains('missing-lang', $this->codes($this->doc('<p>x</p>', 'und')));
        $this->assertNotContains('missing-lang', $this->codes($this->doc('<p>x</p>', 'pl')));
    }

    public function testMissingOrEmptyTitle(): void
    {
        $this->assertContains('missing-title', $this->codes($this->doc('<p>x</p>', 'en', '')));
        $this->assertContains('missing-title', $this->codes('<html lang="en"><body><p>x</p></body></html>'));
    }

    public function testLinkWithoutText(): void
    {
        $codes = $this->codes($this->doc('<a href="https://x.test"></a>'));
        $this->assertContains('link-without-text', $codes);

        $this->assertNotContains('link-without-text', $this->codes($this->doc('<a href="https://x.test"><img src="a.png" alt="Home"></a>')));
        $this->assertNotContains('link-without-text', $this->codes($this->doc('<a href="https://x.test" aria-label="Home"></a>')));
        $this->assertContains('link-without-text', $this->codes($this->doc('<a href="https://x.test"><img src="a.png" alt=""></a>')));
    }

    public function testLayoutTableWithoutRole(): void
    {
        $this->assertContains('layout-table-without-role', $this->codes($this->doc('<table><tr><td>x</td></tr></table>')));
        $this->assertNotContains('layout-table-without-role', $this->codes($this->doc('<table role="presentation"><tr><td>x</td></tr></table>')));
        // A data table with header cells is not a layout table.
        $this->assertNotContains('layout-table-without-role', $this->codes($this->doc('<table><tr><th>a</th></tr><tr><td>1</td></tr></table>')));
    }

    public function testLowContrastOnTheSameElementAndOnAnAncestor(): void
    {
        $this->assertContains('low-contrast', $this->codes($this->doc('<p style="color:#aaaaaa;background-color:#ffffff">x</p>')));
        $this->assertContains('low-contrast', $this->codes($this->doc('<table role="presentation" bgcolor="#336699"><tr><td><p style="color:#444">x</p></td></tr></table>')));
        // No background set: white is assumed.
        $this->assertContains('low-contrast', $this->codes($this->doc('<p style="color:#cccccc">x</p>')));
    }

    public function testSufficientContrastAndLargeTextThreshold(): void
    {
        $this->assertNotContains('low-contrast', $this->codes($this->doc('<p style="color:#767676">x</p>')));
        // #888 on white is 3.5:1: fails for body text, passes for 24px text.
        $this->assertContains('low-contrast', $this->codes($this->doc('<p style="color:#888888;font-size:16px">x</p>')));
        $this->assertNotContains('low-contrast', $this->codes($this->doc('<p style="color:#888888;font-size:24px">x</p>')));
    }

    public function testButtonColoursOfMjmlAreChecked(): void
    {
        $ok = '<td bgcolor="#414141"><a href="https://x.test" style="background:#414141;color:#ffffff">Go</a></td>';
        $bad = '<td bgcolor="#dddddd"><a href="https://x.test" style="background:#dddddd;color:#ffffff">Go</a></td>';

        $this->assertNotContains('low-contrast', $this->codes($this->doc('<table role="presentation"><tr>' . $ok . '</tr></table>')));
        $this->assertContains('low-contrast', $this->codes($this->doc('<table role="presentation"><tr>' . $bad . '</tr></table>')));
    }

    public function testUnparseableColoursAreSkipped(): void
    {
        $this->assertNotContains('low-contrast', $this->codes($this->doc('<p style="color:var(--x)">x</p><p style="color:inherit">y</p>')));
    }
}
