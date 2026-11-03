<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseDeadlineSoon;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\User;
use Ulams\Settings\Models\Setting;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder;
use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder;
use Ulams\TemplatesEmail\Tests\TestCase;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;

/**
 * Templates per language (M6): the seeded defaults in English, Polish and Chinese, the language an e-mail is
 * sent in (course language, tenant default, English, no language) and the per-language test send.
 */
class TemplatesPerLanguageTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Course::class)) {
            $this->markTestSkipped('Courses package not installed');
        }
        $this->seed(PermissionTableSeeder::class);
        // The default texts are translated in the application's language files, which the package tests do not load
        app('translation.loader')->addJsonPath(dirname(__DIR__, 4) . '/resources/lang');
        SettingsVariables::clearSettings();
        Setting::query()->where('group', 'global')->where('key', 'language')->delete();
    }

    /** A fresh tenant: the default templates exist once per language and event, none without a language. */
    private function seedPerLanguage(): void
    {
        TemplateSection::query()->delete();
        Template::query()->delete();
        config([UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.seed_locales' => ['en', 'pl', 'zh']]);
        $this->seed(TemplatesEmailSeeder::class);
    }

    private function tenantLanguage(string $value): void
    {
        Setting::create(['group' => 'global', 'key' => 'language', 'value' => $value, 'public' => true, 'enumerable' => true, 'type' => 'text']);
    }

    /** @return array{subject: string, html: string} what the learner of a course in this language is sent */
    private function deadlineMail(?string $courseLanguage): array
    {
        Mail::fake();
        $user = User::factory()->create();
        $course = Course::factory()->create([
            'title' => 'Algebra',
            'language' => $courseLanguage,
            'status' => CourseStatusEnum::PUBLISHED,
            'active_to' => Carbon::now()->addDays(3),
        ]);
        $user->courses()->save($course);

        app(TemplateEventListener::class)->handle(new CourseDeadlineSoon($user, $course));

        $sent = [];
        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use (&$sent, $user) {
            $this->assertTrue($mailable->hasTo($user->email));
            $sent = ['subject' => $mailable->subject, 'html' => (string) $mailable->getHtml()];

            return true;
        });

        return $sent;
    }

    public function testTheSeederWritesEveryEventInEnglishPolishAndChinese(): void
    {
        $this->seedPerLanguage();

        $deadline = Template::query()->where('event', CourseDeadlineSoon::class)->where('channel', EmailChannel::class)->orderBy('id')->get();

        $this->assertSame(['en', 'pl', 'zh'], $deadline->pluck('locale')->all());
        $this->assertTrue($deadline->every(fn (Template $t) => $t->default));
        $this->assertSame(0, Template::query()->where('channel', EmailChannel::class)->whereNull('locale')->count());
        $perEvent = Template::query()->where('channel', EmailChannel::class)->selectRaw('event, count(*) as total')->groupBy('event')->pluck('total');
        $this->assertTrue($perEvent->every(fn ($total) => (int) $total === 3), 'every event has exactly three templates');
    }

    public function testEachSeededTemplateIsWrittenAndMarkedInItsOwnLanguage(): void
    {
        $this->seedPerLanguage();

        foreach (['en', 'pl', 'zh'] as $locale) {
            $template = Template::query()->where('event', CourseDeadlineSoon::class)->where('locale', $locale)->firstOrFail();
            $sections = $template->sections->pluck('content', 'key');
            $this->assertSame(trans('Deadline for course ":course"', ['course' => '@VarCourseTitle'], $locale), $sections['title']);
            $this->assertStringContainsString('<mjml lang="' . $locale . '" dir="ltr">', $sections['content']);
        }
        $this->assertStringContainsString('Termin ukończenia kursu', Template::query()->where('event', CourseDeadlineSoon::class)->where('locale', 'pl')->first()->sections->firstWhere('key', 'title')->content);
        $this->assertStringContainsString('截止日期', Template::query()->where('event', CourseDeadlineSoon::class)->where('locale', 'zh')->first()->sections->firstWhere('key', 'title')->content);
        $this->assertSame('en', app()->getLocale());
    }

    public function testTheSeederDoesNotAddLanguagesToAnEventThatAlreadyHasATemplate(): void
    {
        // The tenant of the setUp has its templates without a language: an upgrade must not change what it sends.
        config([UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.seed_locales' => ['en', 'pl', 'zh']]);
        $count = Template::query()->where('channel', EmailChannel::class)->count();

        $this->seed(TemplatesEmailSeeder::class);

        $this->assertSame($count, Template::query()->where('channel', EmailChannel::class)->count());
    }

    public function testAnEmailIsSentInTheLanguageOfTheCourse(): void
    {
        $this->seedPerLanguage();

        foreach (['en', 'pl', 'zh'] as $locale) {
            $mail = $this->deadlineMail($locale);
            $this->assertSame(trans('Deadline for course ":course"', ['course' => 'Algebra'], $locale), $mail['subject'], $locale);
            $this->assertStringContainsString('lang="' . $locale . '"', $mail['html']);
        }
    }

    public function testACourseInAnotherLanguageOrWithoutOneFallsBackToTheTenantDefaultThenEnglish(): void
    {
        $this->seedPerLanguage();
        $english = trans('Deadline for course ":course"', ['course' => 'Algebra'], 'en');
        $polish = trans('Deadline for course ":course"', ['course' => 'Algebra'], 'pl');

        $this->assertSame($english, $this->deadlineMail('de')['subject'], 'no template for German, no tenant language: English');
        $this->assertSame($english, $this->deadlineMail(null)['subject']);

        $this->tenantLanguage('pl');
        $this->assertSame($polish, $this->deadlineMail('de')['subject'], 'the tenant default language before English');
        $this->assertSame($polish, $this->deadlineMail(null)['subject']);
        $this->assertSame($english, $this->deadlineMail('en')['subject'], 'the course language before the tenant default');
    }

    public function testATenantWithoutLanguageTemplatesKeepsSendingItsOwnTemplate(): void
    {
        // The templates of the setUp have no language: whatever the course language, they are what is sent.
        $english = trans('Deadline for course ":course"', ['course' => 'Algebra'], 'en');

        foreach (['pl', 'zh', null] as $locale) {
            $this->assertSame($english, $this->deadlineMail($locale)['subject']);
        }
    }

    public function testAnEditedTemplateInTheLanguageOfTheCourseIsWhatIsSent(): void
    {
        $this->seedPerLanguage();
        $template = Template::query()->where('event', CourseDeadlineSoon::class)->where('locale', 'pl')->firstOrFail();
        TemplateSection::where('template_id', $template->getKey())->where('key', 'title')->update(['content' => 'Pilne: @VarCourseTitle']);

        $this->assertSame('Pilne: Algebra', $this->deadlineMail('pl')['subject']);
        $this->assertSame(trans('Deadline for course ":course"', ['course' => 'Algebra'], 'en'), $this->deadlineMail('en')['subject']);
    }

    public function testTheTestSendCarriesTheLanguageOfTheDraft(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $body = [
            'event' => ManuallyTriggeredEvent::class,
            'title' => 'Hello @VarUserName',
            'content' => '<mjml lang="pl"><mj-body><mj-section><mj-column><mj-text>Cześć @VarUserName</mj-text></mj-column></mj-section></mj-body></mjml>',
            'locale' => 'PL',
        ];

        $data = $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/test-send', $body)->assertOk()->json('data');

        $this->assertSame('pl', $data['locale']);
        $this->assertSame($admin->email, $data['recipient']);
        $this->assertSame('en', app()->getLocale(), 'the application language is put back');
        Mail::assertSent(EmailMailable::class, fn (EmailMailable $mail) => $mail->hasTo($admin->email) && str_starts_with($mail->subject, '[Test] '));
        Mail::assertSentCount(1);
    }

    public function testRenderRendersTheSampleValuesInTheRequestedLanguageAndNeedsNoLanguage(): void
    {
        $admin = $this->makeAdmin();
        $body = ['event' => ManuallyTriggeredEvent::class, 'title' => 'Hello', 'content' => '<p>@VarUserName</p>'];

        $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/render', $body)->assertOk()->assertJsonPath('data.locale', null);
        $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/render', $body + ['locale' => 'zh'])->assertOk()->assertJsonPath('data.locale', 'zh');
        $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/render', $body + ['locale' => null])->assertOk()->assertJsonPath('data.locale', null);
        $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/render', $body + ['locale' => 'chinese'])->assertStatus(422)->assertJsonValidationErrors('locale');
    }

    public function testAUserWithoutTemplateUpdateCannotTestSendInALanguage(): void
    {
        Mail::fake();
        $body = ['event' => ManuallyTriggeredEvent::class, 'title' => 'Hello', 'content' => '<p>@VarUserName</p>', 'locale' => 'pl'];

        $this->postJson('/api/admin/templates/email/test-send', $body)->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/admin/templates/email/test-send', $body)->assertForbidden();
        Mail::assertNothingSent();
    }
}
