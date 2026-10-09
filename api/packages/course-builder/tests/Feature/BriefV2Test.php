<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Spatie\Permission\Models\Permission;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Pipeline\BriefService;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Settings\Models\Setting;

/** Course Brief v2: price, theme and site, the editable brief and the theme step of the apply. */
class BriefV2Test extends TestCase
{
    private function siteAdmin()
    {
        $user = $this->author();
        Permission::findOrCreate('settings_manage', 'api');
        $user->givePermissionTo('settings_manage');

        return $user;
    }

    public function testV1BriefIsReadAsV2WithoutRewritingIt(): void
    {
        $author = $this->author();
        $session = $this->newSession($author);
        $v1 = (new BriefService(app(\Ulams\CourseBuilder\Blueprint\SchemaRegistry::class)))->defaults($session);
        $this->assertArrayNotHasKey('pricing', $v1);
        $session->brief = $v1;
        $session->save();

        $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/brief")
            ->assertOk()->assertJsonPath('data.brief.pricing.mode', 'free');
        $this->assertArrayNotHasKey('pricing', $session->refresh()->brief);
    }

    public function testInterviewAsksPricingAndOffersThemeOnlyToSiteAdmins(): void
    {
        $session = $this->uploaded($this->author());
        $keys = array_column($session->stateValue('interview.questions'), 'key');
        $this->assertSame(['audience', 'level', 'duration', 'tone', 'assessments', 'language', 'pricing'], $keys);

        $admin = $this->siteAdmin();
        $session = $this->uploaded($admin);
        $questions = collect($session->stateValue('interview.questions'));
        $this->assertSame('ThemePicker', $questions->firstWhere('key', 'theme')['component']);
        $this->assertSame('PriceInput', $questions->firstWhere('key', 'pricing')['component']);
    }

    public function testPriceAndThemeAnswersFillTheBrief(): void
    {
        $admin = $this->siteAdmin();
        $session = $this->uploaded($admin);
        $this->action($admin, $session, 'answer', 'interview', ['key' => 'pricing', 'value' => ['mode' => 'paid', 'amountMinor' => 4900, 'currency' => 'usd']])->assertStatus(202);
        $this->action($admin, $session, 'answer', 'interview', ['key' => 'theme', 'value' => ['preset' => 'nightsky', 'accent' => '#FFAA00']])->assertStatus(202);

        $brief = $session->refresh()->brief;
        $this->assertSame(['mode' => 'paid', 'amountMinor' => 4900, 'currency' => 'USD'], $brief['pricing']);
        $this->assertSame(['preset' => 'nightsky', 'accent' => '#ffaa00'], $brief['theme']);
        $this->assertSame('author', $brief['decidedBy']['pricing']);

        $this->action($admin, $session, 'answer', 'interview', ['key' => 'pricing', 'value' => 'bogus'])->assertStatus(202);
        $this->assertSame('paid', $session->refresh()->brief['pricing']['mode'], 'an invalid answer changes nothing');
    }

    public function testDecideForMeKeepsItFreeAndKeepsTheCurrentTheme(): void
    {
        $admin = $this->siteAdmin();
        $session = $this->toOutline($admin);
        $this->assertSame(['mode' => 'free'], $session->brief['pricing']);
        $this->assertArrayHasKey($session->brief['theme']['preset'], array_flip(BriefService::THEMES));
        $this->assertSame('default', $session->brief['decidedBy']['pricing']);
    }

    public function testPutBriefReplacesPriceAndDoesNotMarkStagesStale(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $url = "/api/admin/course-builder/sessions/{$session->id}/brief";

        $this->actingAs($author, 'api')->putJson($url, ['brief' => ['pricing' => ['mode' => 'paid', 'amountMinor' => 1900, 'currency' => 'EUR']]])
            ->assertOk()->assertJsonPath('data.stale', false)->assertJsonPath('data.brief.pricing.amountMinor', 1900);
        $this->actingAs($author, 'api')->putJson($url, ['brief' => ['pricing' => ['mode' => 'free']]])
            ->assertOk()->assertJsonPath('data.brief.pricing', ['mode' => 'free']);
        $this->actingAs($author, 'api')->putJson($url, ['brief' => ['theme' => ['preset' => 'nope']]])->assertStatus(422);
        $this->actingAs($author, 'api')->putJson($url, ['brief' => ['pricing' => ['mode' => 'paid', 'amountMinor' => -5]]])->assertStatus(422);
        $this->actingAs($author, 'api')->putJson($url, ['brief' => ['site' => ['mode' => 'new', 'slug' => 'Bad Slug']]])->assertStatus(422);
        $this->actingAs($author, 'api')->putJson($url, ['brief' => ['tone' => 'academic']])->assertOk()->assertJsonPath('data.stale', true);
    }

    public function testPriceThemeAndSiteStayOutOfThePrompt(): void
    {
        $brief = ['tone' => 'friendly', 'pricing' => ['mode' => 'paid'], 'theme' => ['preset' => 'coffee'], 'site' => ['mode' => 'current'], 'decidedBy' => ['tone' => 'author', 'pricing' => 'author']];
        $this->assertSame(['tone' => 'friendly', 'decidedBy' => ['tone' => 'author']], PromptContext::contentBrief($brief));
    }

    public function testApplyWritesTheThemeOnlyForSiteAdmins(): void
    {
        $admin = $this->siteAdmin();
        $session = $this->toApplyReview($admin);
        $this->actingAs($admin, 'api')->putJson("/api/admin/course-builder/sessions/{$session->id}/brief", ['brief' => ['theme' => ['preset' => 'oncall', 'accent' => '#58a6ff']]])->assertOk();
        $version = $session->refresh()->current_version_id;
        $this->action($admin, $session, 'approve_apply', "apply-{$version}", ['versionId' => $version])->assertStatus(202);

        $this->assertSame('oncall', Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->value('value'));
        $this->assertSame('#58a6ff', Setting::query()->where(['group' => 'theme', 'key' => 'accent'])->value('value'));
        $this->assertSame([], $session->refresh()->stateValue('applyNotes'));
    }

    public function testApplySkipsTheThemeWithANoteForOtherAuthors(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $this->actingAs($author, 'api')->putJson("/api/admin/course-builder/sessions/{$session->id}/brief", ['brief' => ['theme' => ['preset' => 'oncall']]])->assertOk();
        $version = $session->refresh()->current_version_id;
        $this->action($author, $session, 'approve_apply', "apply-{$version}", ['versionId' => $version])->assertStatus(202);

        $this->assertNull(Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->value('value'));
        $this->assertStringContainsString('only site admins', $session->refresh()->stateValue('applyNotes.0'));
        $this->assertSame(Session::APPLIED, $session->status);
    }

    public function testASessionOfAnotherAuthorIsNotEditable(): void
    {
        $session = $this->newSession($this->author());
        $this->actingAs($this->author(), 'api')->putJson("/api/admin/course-builder/sessions/{$session->id}/brief", ['brief' => ['pricing' => ['mode' => 'free']]])
            ->assertStatus(403);
    }
}
