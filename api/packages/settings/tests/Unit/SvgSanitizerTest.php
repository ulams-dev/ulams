<?php

namespace Ulams\Settings\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ulams\Settings\Support\SvgSanitizer;
use Ulams\Settings\Support\UnsafeSvg;
use Ulams\Settings\Tests\TestCase;

/** The SVG logo allow-list: shapes pass (and are re-serialised), everything that can run or fetch is refused. */
class SvgSanitizerTest extends TestCase
{
    private const OK = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40" role="img" aria-label="Acme"><defs><linearGradient id="g"><stop offset="0" stop-color="#c8102e"/><stop offset="1" stop-color="#600"/></linearGradient></defs><rect width="120" height="40" rx="6" fill="url(#g)"/><text x="10" y="26" font-family="serif" font-size="18" fill="#fff">Acme</text><use href="#g"/></svg>';

    #[Test]
    public function testShapesGradientsAndTextPass(): void
    {
        $out = SvgSanitizer::sanitize(self::OK);
        $this->assertStringContainsString('<rect', $out);
        $this->assertStringContainsString('url(#g)', $out);
        $this->assertSame([120.0, 40.0], SvgSanitizer::size($out));
    }

    #[Test]
    public function testAnXmlDeclarationAndCommentsAreFine(): void
    {
        $out = SvgSanitizer::sanitize("\xEF\xBB\xBF<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!-- made in an editor --><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"10\" height=\"10\"><circle r=\"4\"/></svg>");
        $this->assertStringNotContainsString('made in an editor', $out);
        $this->assertStringNotContainsString('<?xml', $out);
        $this->assertSame([10.0, 10.0], SvgSanitizer::size($out));
    }

    public static function unsafe(): array
    {
        $ns = 'xmlns="http://www.w3.org/2000/svg"';

        return [
            'script element' => ["<svg $ns><script>alert(1)</script></svg>", 'script'],
            'script in a group' => ["<svg $ns><g><script>alert(1)</script></g></svg>", 'script'],
            'style element' => ["<svg $ns><style>*{background:url(http://evil/x)}</style></svg>", 'style'],
            'foreignObject' => ["<svg $ns><foreignObject><div xmlns=\"http://www.w3.org/1999/xhtml\">x</div></foreignObject></svg>", 'foreignObject'],
            'anchor' => ["<svg $ns><a href=\"javascript:alert(1)\"><rect width=\"1\" height=\"1\"/></a></svg>", '<a>'],
            'embedded image' => ["<svg $ns><image href=\"http://evil/x.png\"/></svg>", 'image'],
            'iframe' => ["<svg $ns><iframe src=\"http://evil\"/></svg>", 'iframe'],
            'animate rewrites href' => ["<svg $ns><use href=\"#a\"><animate attributeName=\"href\" to=\"http://evil\"/></use></svg>", 'animate'],
            'set rewrites href' => ["<svg $ns><set attributeName=\"onload\" to=\"alert(1)\"/></svg>", 'set'],
            'onload' => ["<svg $ns onload=\"alert(1)\"><rect/></svg>", 'onload'],
            'onclick on a shape' => ["<svg $ns><rect onclick=\"alert(1)\" width=\"1\" height=\"1\"/></svg>", 'onclick'],
            'ONLOAD upper case' => ["<svg $ns ONLOAD=\"alert(1)\"><rect/></svg>", 'ONLOAD'],
            'external use' => ["<svg $ns><use href=\"http://evil/x.svg#a\"/></svg>", 'href'],
            'xlink external use' => ["<svg $ns xmlns:xlink=\"http://www.w3.org/1999/xlink\"><use xlink:href=\"https://evil/x.svg#a\"/></svg>", 'href'],
            'javascript use' => ["<svg $ns><use href=\"javascript:alert(1)\"/></svg>", 'href'],
            'href on a shape' => ["<svg $ns><rect href=\"#a\"/></svg>", 'href'],
            'url to another address' => ["<svg $ns><rect fill=\"url(http://evil/x)\"/></svg>", 'url()'],
            'url in a style attribute' => ["<svg $ns><rect style=\"fill:url('https://evil/x')\"/></svg>", 'url()'],
            'data uri' => ["<svg $ns><rect fill=\"url(data:image/svg+xml;base64,AAAA)\"/></svg>", 'fill'],
            'expression' => ["<svg $ns><rect style=\"width:expression(alert(1))\"/></svg>", 'style'],
            'css import' => ["<svg $ns><rect style=\"@import 'x'\"/></svg>", 'style'],
            'javascript value' => ["<svg $ns><rect fill=\"javascript:alert(1)\"/></svg>", 'fill'],
            'entity encoded javascript' => ["<svg $ns><rect fill=\"&#106;avascript:alert(1)\"/></svg>", 'fill'],
            'unknown attribute' => ["<svg $ns><rect data-x=\"1\"/></svg>", 'data-x'],
            'other namespace element' => ["<svg $ns><x:y xmlns:x=\"http://evil/ns\"/></svg>", 'x:y'],
            'foreign namespace declaration' => ["<svg $ns xmlns:x=\"http://evil/ns\"><rect/></svg>", 'namespace'],
            'html root' => ['<html xmlns="http://www.w3.org/1999/xhtml"><body>x</body></html>', 'svg'],
            'svg without namespace' => ['<svg><rect/></svg>', 'svg'],
            'doctype' => ["<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\"><svg $ns/>", 'DOCTYPE'],
            'entity bomb' => ["<!DOCTYPE svg [<!ENTITY a \"aaaaaaaaaa\"><!ENTITY b \"&a;&a;&a;&a;\">]><svg $ns><text>&b;</text></svg>", 'DOCTYPE'],
            'xxe' => ["<!DOCTYPE svg [<!ENTITY x SYSTEM \"file:///etc/passwd\">]><svg $ns><text>&x;</text></svg>", 'DOCTYPE'],
            'processing instruction' => ["<?xml-stylesheet href=\"http://evil/x.css\"?><svg $ns/>", 'processing'],
            'text in a shape' => ["<svg $ns><rect>hello</rect></svg>", 'text'],
            'not xml' => ['this is not svg', 'well-formed'],
            'truncated' => ["<svg $ns><rect", 'well-formed'],
            'cdata script' => ["<svg $ns><script><![CDATA[alert(1)]]></script></svg>", 'script'],
        ];
    }

    #[Test]
    #[DataProvider('unsafe')]
    public function testRefusesAndSaysWhat(string $svg, string $mentions): void
    {
        try {
            SvgSanitizer::sanitize($svg);
            $this->fail('The SVG should have been refused.');
        } catch (UnsafeSvg $e) {
            $this->assertStringContainsStringIgnoringCase($mentions, $e->getMessage());
        }
    }

    #[Test]
    public function testTooManyElementsAndTooDeepAreRefused(): void
    {
        $wide = '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat('<rect/>', 2001) . '</svg>';
        $deep = '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat('<g>', 40) . str_repeat('</g>', 40) . '</svg>';
        foreach ([$wide, $deep] as $svg) {
            try {
                SvgSanitizer::sanitize($svg);
                $this->fail('Too complex.');
            } catch (UnsafeSvg $e) {
                $this->assertStringContainsString('complex', $e->getMessage());
            }
        }
    }
}
