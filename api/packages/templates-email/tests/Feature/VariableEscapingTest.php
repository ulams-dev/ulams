<?php

namespace Ulams\TemplatesEmail\Tests\Feature;

use Illuminate\Support\HtmlString;
use Ulams\Settings\Models\Setting;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Core\EmailVariables;
use Ulams\TemplatesEmail\Tests\Mocks\TestEvent;
use Ulams\TemplatesEmail\Tests\TestCase;

class VariableEscapingTest extends TestCase
{
    private function template(string $title, string $contentHtml): Template
    {
        $template = Template::factory()->create([
            'channel' => EmailChannel::class,
            'event' => TestEvent::class,
        ]);
        TemplateSection::factory(['key' => 'title', 'template_id' => $template->getKey(), 'content' => $title])->create();
        TemplateSection::factory(['key' => 'contentHtml', 'template_id' => $template->getKey(), 'content' => $contentHtml])->create();

        return $template->refresh();
    }

    public function testEscapesVariableValuesInHtmlButNotInTheSubject(): void
    {
        $template = $this->template('Hello @VarUserName', '<p>Hello @VarUserName</p>');
        $name = '<script>alert("x")</script> O\'Brien & Sons';

        $sections = $template->generateContent(['@VarUserName' => $name]);

        $this->assertSame(
            '<p>Hello &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; O&#039;Brien &amp; Sons</p>',
            $sections['contentHtml']
        );
        $this->assertStringNotContainsString('<script>', $sections['contentHtml']);
        // The subject is plain text: no entities, no double escaping.
        $this->assertSame('Hello ' . $name, $sections['title']);
    }

    public function testEscapesQuotesInsideAttributes(): void
    {
        $template = $this->template('t', '<a href="@VarActionLink">go</a>');

        $sections = $template->generateContent(['@VarActionLink' => 'https://x.test/?a=1&b="><script>']);

        $this->assertSame('<a href="https://x.test/?a=1&amp;b=&quot;&gt;&lt;script&gt;">go</a>', $sections['contentHtml']);
    }

    public function testHtmlableValuesAreKeptRaw(): void
    {
        $template = $this->template('t', '<div>@VarProductables</div><p>@VarUserName</p>');

        $sections = $template->generateContent([
            '@VarProductables' => new HtmlString('<table><tr><td>1</td></tr></table>'),
            '@VarUserName' => '<b>x</b>',
        ]);

        $this->assertSame('<div><table><tr><td>1</td></tr></table></div><p>&lt;b&gt;x&lt;/b&gt;</p>', $sections['contentHtml']);
    }

    public function testNonStringValuesAreStillSubstituted(): void
    {
        $template = $this->template('t', '<p>@VarPercentage% @VarNothing</p>');

        $sections = $template->generateContent(['@VarPercentage' => 42, '@VarNothing' => null]);

        $this->assertSame('<p>42% </p>', $sections['contentHtml']);
    }

    public function testMarkdownSettingsAreRawHtmlInEmailVariables(): void
    {
        SettingsVariables::clearSettings();
        Setting::create([
            'group' => 'mail', 'key' => 'footer', 'value' => 'Hello **world**',
            'public' => true, 'enumerable' => true, 'type' => 'markdown',
        ]);
        Setting::create([
            'group' => 'mail', 'key' => 'signature', 'value' => 'A <b> & B',
            'public' => true, 'enumerable' => true, 'type' => 'text',
        ]);
        SettingsVariables::clearSettings();

        $variables = EmailVariables::mockedVariables();
        $template = $this->template('t', '@GlobalSettingsFooterMarkdown|@GlobalSettingsSignatureText');
        $sections = $template->generateContent($variables);

        $this->assertStringContainsString('<strong>world</strong>', $sections['contentHtml']);
        $this->assertStringContainsString('A &lt;b&gt; &amp; B', $sections['contentHtml']);
        SettingsVariables::clearSettings();
    }
}
