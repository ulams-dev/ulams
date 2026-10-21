<?php

namespace Ulams\TemplatesEmail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\TemplatesEmail\Core\EmailVariables;

class EmailVariablesLanguageTest extends TestCase
{
    public function testARootWithoutLangGetsTheLanguageAndDirection(): void
    {
        $this->assertSame(
            '<mjml lang="pl" dir="ltr"><mj-body></mj-body></mjml>',
            EmailVariables::withLanguage('<mjml><mj-body></mj-body></mjml>', 'pl')
        );
        $this->assertSame(
            '<mjml owa="desktop" lang="en" dir="ltr"><mj-body></mj-body></mjml>',
            EmailVariables::withLanguage('<mjml owa="desktop"><mj-body></mj-body></mjml>', 'en_GB')
        );
    }

    public function testRightToLeftLanguagesGetDirRtl(): void
    {
        $this->assertStringContainsString(
            'lang="ar" dir="rtl"',
            EmailVariables::withLanguage('<mjml><mj-body></mj-body></mjml>', 'ar')
        );
    }

    public function testARootThatSetsLangIsLeftAlone(): void
    {
        $mjml = "<mjml lang=\"de\">\n<mj-body></mj-body></mjml>";

        $this->assertSame($mjml, EmailVariables::withLanguage($mjml, 'pl'));
    }

    public function testOnlyTheRootIsChangedAndOddLocalesAreIgnored(): void
    {
        $mjml = '<mjml><mj-body><mj-text><mjml-like></mjml-like></mj-text></mj-body></mjml>';

        $this->assertSame($mjml, EmailVariables::withLanguage($mjml, ''));
        $this->assertSame($mjml, EmailVariables::withLanguage($mjml, '9x'));
        $this->assertSame(1, substr_count(EmailVariables::withLanguage($mjml, 'fr'), 'lang="fr"'));
    }
}
