<?php

namespace Ulams\TemplatesEmail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\TemplatesEmail\Core\EmailChannel;

class EmailChannelIsMjmlTest extends TestCase
{
    public function testMjmlWithOrWithoutAttributesIsMjml(): void
    {
        $this->assertTrue(EmailChannel::isMjml('<mjml><mj-body></mj-body></mjml>'));
        $this->assertTrue(EmailChannel::isMjml("<mjml lang=\"pl\">\n<mj-body></mj-body></mjml>"));
        $this->assertTrue(EmailChannel::isMjml("<!-- c -->\n<MJML\n lang=\"en\">"));
    }

    public function testHtmlIsNotMjml(): void
    {
        $this->assertFalse(EmailChannel::isMjml('<p>Hi</p>'));
        $this->assertFalse(EmailChannel::isMjml('<mjml-like>x</mjml-like>'));
        $this->assertFalse(EmailChannel::isMjml(''));
    }
}
