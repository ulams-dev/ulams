<?php

namespace Ulams\Templates\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Settings\Models\Setting;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Events\EventWrapper;
use Ulams\Templates\Facades\Template as TemplateFacade;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\Templates\Support\TemplateLocale;
use Ulams\Templates\Tests\Mock\TestChannel;
use Ulams\Templates\Tests\Mock\TestEventWithGetters;
use Ulams\Templates\Tests\Mock\TestEventWithLanguage;
use Ulams\Templates\Tests\Mock\TestVariables;
use Ulams\Templates\Tests\Mock\TestVariablesLocalized;
use Ulams\Templates\Tests\Mock\TestVariablesWithAssignableClass;
use Ulams\Templates\Services\Contracts\TemplateServiceContract;
use Ulams\Templates\Tests\TestCase;

/**
 * Templates per language (M6): the migration, the language an event is sent in, the fallback order and the
 * default templates written per language.
 */
class TemplateLocaleTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        SettingsVariables::clearSettings();
        Setting::query()->where('group', 'global')->where('key', 'language')->delete();
        TestChannel::$handledNotifications = [];
        TemplateFacade::register(TestEventWithLanguage::class, TestChannel::class, TestVariables::class);
    }

    private function tenantLanguage(string $value): void
    {
        Setting::create(['group' => 'global', 'key' => 'language', 'value' => $value, 'public' => true, 'enumerable' => true, 'type' => 'text']);
    }

    private function template(?string $locale, string $title, bool $default = true, string $event = TestEventWithLanguage::class): Template
    {
        $template = Template::factory()->create([
            'event' => $event,
            'channel' => TestChannel::class,
            'default' => $default,
            'locale' => $locale,
        ]);
        foreach (['title' => $title, 'content' => '<p>Mail ' . TestVariables::VAR_USER_EMAIL . ' ' . TestVariables::VAR_FRIEND_EMAIL . '</p>'] as $key => $content) {
            TemplateSection::factory()->create(['template_id' => $template->getKey(), 'key' => $key, 'content' => $content]);
        }

        return $template;
    }

    /** The title of the template the event was sent with. */
    private function sentTitle(?string $courseLanguage): ?string
    {
        TestChannel::$handledNotifications = [];
        $user = $this->makeStudent();
        TemplateFacade::handleEvent(new EventWrapper(new TestEventWithLanguage($user, $this->makeStudent(), $courseLanguage)));

        return TestChannel::$handledNotifications[0]['title'] ?? null;
    }

    public function testTheColumnIsNullableIndexedAndExistingRowsKeepNull(): void
    {
        $this->assertTrue(Schema::hasColumn('templates', 'locale'));
        $this->assertSame('locale', collect(Schema::getIndexes('templates'))->first(fn ($i) => $i['columns'] === ['locale'])['columns'][0] ?? null);
        $this->assertNull(Template::factory()->create()->refresh()->locale);
    }

    public function testTheMigrationGoesDownAndUpAgain(): void
    {
        $existing = Template::factory()->create(['name' => 'legacy']);
        $migration = require __DIR__ . '/../../database/migrations/2026_10_26_000001_add_locale_to_templates_table.php';

        $migration->down();
        $this->assertFalse(Schema::hasColumn('templates', 'locale'));
        $migration->down(); // down twice is harmless

        $migration->up();
        $migration->up(); // up twice is harmless
        $this->assertTrue(Schema::hasColumn('templates', 'locale'));
        $this->assertNull($existing->fresh()->locale);
        $this->assertNotNull(collect(Schema::getIndexes('templates'))->first(fn ($i) => $i['columns'] === ['locale']));
    }

    public function testNormalize(): void
    {
        $this->assertSame('pl', TemplateLocale::normalize('pl'));
        $this->assertSame('pl', TemplateLocale::normalize(' PL_pl '));
        $this->assertSame('zh', TemplateLocale::normalize('zh-CN'));
        $this->assertNull(TemplateLocale::normalize(null));
        $this->assertNull(TemplateLocale::normalize(''));
        $this->assertNull(TemplateLocale::normalize('polish'));
        $this->assertNull(TemplateLocale::normalize('9x'));
    }

    public function testTheFallbackChain(): void
    {
        $this->assertSame(['pl', 'en', null], TemplateLocale::fallbackChain('pl'));
        $this->assertSame(['en', null], TemplateLocale::fallbackChain(null));
        $this->assertSame(['en', null], TemplateLocale::fallbackChain('en'));

        $this->tenantLanguage('zh');
        $this->assertSame(['pl', 'zh', 'en', null], TemplateLocale::fallbackChain('pl-PL'));
        $this->assertSame(['zh', 'en', null], TemplateLocale::fallbackChain('zh'));
        $this->assertSame(['zh', 'en', null], TemplateLocale::fallbackChain(null));
    }

    public function testTheLanguageOfAnEvent(): void
    {
        $user = $this->makeStudent();
        $friend = $this->makeStudent();

        $this->assertSame('pl', (new EventWrapper(new TestEventWithLanguage($user, $friend, 'pl')))->language());
        $this->assertSame('zh', (new EventWrapper(new TestEventWithLanguage($user, $friend, 'ZH')))->language());
        $this->assertNull((new EventWrapper(new TestEventWithLanguage($user, $friend)))->language());
        $this->assertNull((new EventWrapper(new TestEventWithGetters($user, $friend)))->language());
        $this->assertNull((new EventWrapper(new TestEventWithLanguage($user, $friend, 'not a language')))->language());
    }

    public function testTheTemplateInTheLanguageOfTheCourseIsSent(): void
    {
        $this->template(null, 'Universal');
        $this->template('en', 'English');
        $this->template('pl', 'Polski');
        $this->template('zh', '中文');

        $this->assertSame('Polski', $this->sentTitle('pl'));
        $this->assertSame('中文', $this->sentTitle('zh'));
        $this->assertSame('English', $this->sentTitle('en'));
    }

    public function testACourseLanguageWithoutATemplateFallsBackToTheTenantDefaultThenEnglishThenNoLanguage(): void
    {
        $universal = $this->template(null, 'Universal');
        $this->assertSame('Universal', $this->sentTitle('pl'), 'only the template without a language exists');

        $english = $this->template('en', 'English');
        $this->assertSame('English', $this->sentTitle('pl'), 'English before the template without a language');
        $this->assertSame('English', $this->sentTitle(null));

        $this->template('zh', '中文');
        $this->tenantLanguage('zh');
        $this->assertSame('中文', $this->sentTitle('pl'), 'the tenant default language before English');
        $this->assertSame('中文', $this->sentTitle(null), 'a course without a language uses the tenant default');
        $this->assertSame('English', $this->sentTitle('en'));

        $english->delete();
        $this->assertSame('中文', $this->sentTitle('en'));
        $this->assertNotNull($universal->fresh());
    }

    public function testATemplateThatIsNotTheDefaultOfItsLanguageIsNotSent(): void
    {
        $this->template(null, 'Universal');
        $this->template('pl', 'Polski (draft)', false);

        $this->assertSame('Universal', $this->sentTitle('pl'));
    }

    public function testATemplateAssignedToAModelStillWinsOverTheLanguage(): void
    {
        // Assignment is an explicit choice of the operator: the language does not override it.
        TemplateFacade::register(TestEventWithGetters::class, TestChannel::class, TestVariablesWithAssignableClass::class);
        $this->template('en', 'English', true, TestEventWithGetters::class);
        $assigned = $this->template('zh', '中文', false, TestEventWithGetters::class);
        $user = $this->makeStudent();
        app(TemplateServiceContract::class)->assignTemplateToModel($assigned, $user->getKey());

        TemplateFacade::handleEvent(new EventWrapper(new TestEventWithGetters($user, $this->makeStudent())));
        $this->assertSame('中文', TestChannel::$handledNotifications[0]['title']);

        TestChannel::$handledNotifications = [];
        TemplateFacade::handleEvent(new EventWrapper(new TestEventWithGetters($this->makeStudent(), $this->makeStudent())));
        $this->assertSame('English', TestChannel::$handledNotifications[0]['title'], 'a user without an assignment gets the language default');
    }

    public function testTheLegacyDefaultLookupPrefersTheTemplateWithoutALanguage(): void
    {
        $localized = $this->template('pl', 'Polski');
        $universal = $this->template(null, 'Universal');
        $repository = app(\Ulams\Templates\Repository\Contracts\TemplateRepositoryContract::class);

        $this->assertSame($universal->getKey(), $repository->findTemplateDefault(TestEventWithLanguage::class, TestChannel::class)->getKey());
        $this->assertSame($localized->getKey(), $repository->findTemplateDefaultForLocale(TestEventWithLanguage::class, TestChannel::class, 'pl')->getKey());
        $this->assertNull($repository->findTemplateDefaultForLocale(TestEventWithLanguage::class, TestChannel::class, 'zh'));
    }

    public function testDefaultTemplatesAreWrittenPerLanguage(): void
    {
        TemplateFacade::register(TestEventWithGetters::class, TestChannel::class, TestVariablesLocalized::class);

        TemplateFacade::createDefaultTemplatesForChannel(TestChannel::class, ['en', 'pl', 'zh']);

        $templates = Template::query()->where('event', TestEventWithGetters::class)->orderBy('id')->get();
        $this->assertSame(['en', 'pl', 'zh'], $templates->pluck('locale')->all());
        $this->assertTrue($templates->every(fn (Template $t) => $t->default));
        foreach ($templates as $template) {
            $sections = $template->sections->pluck('content', 'key');
            $this->assertSame('Title in ' . $template->locale, $sections['title']);
            $this->assertStringContainsString('Content in ' . $template->locale, $sections['content']);
        }
        $this->assertSame('en', app()->getLocale(), 'the application language is put back');

        TemplateFacade::createDefaultTemplatesForChannel(TestChannel::class, ['en', 'pl', 'zh']);
        $this->assertSame(3, Template::query()->where('event', TestEventWithGetters::class)->count(), 'a second run adds nothing');
    }

    public function testDefaultTemplatesWithoutLanguagesAreWrittenAsBefore(): void
    {
        TemplateFacade::register(TestEventWithGetters::class, TestChannel::class, TestVariablesLocalized::class);

        TemplateFacade::createDefaultTemplatesForChannel(TestChannel::class);

        $templates = Template::query()->where('event', TestEventWithGetters::class)->get();
        $this->assertCount(1, $templates);
        $this->assertNull($templates->first()->locale);
    }

    public function testAnEventWithADefaultTemplateGetsNoLanguageTemplatesSeeded(): void
    {
        TemplateFacade::register(TestEventWithGetters::class, TestChannel::class, TestVariablesLocalized::class);
        $this->template(null, 'Edited by the operator', true, TestEventWithGetters::class);

        TemplateFacade::createDefaultTemplatesForChannel(TestChannel::class, ['en', 'pl', 'zh']);

        $this->assertSame(1, Template::query()->where('event', TestEventWithGetters::class)->count());
    }
}
