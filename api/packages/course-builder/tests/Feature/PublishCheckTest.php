<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Publish\AccentContrast;
use Ulams\CourseBuilder\Publish\LandingValidator;
use Ulams\CourseBuilder\Publish\MarkdownAccessibility;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Pages\Models\Page;

class PublishCheckTest extends TestCase
{
    private function url(Session $s, string $path): string
    {
        return "/api/admin/course-builder/sessions/{$s->id}/{$path}";
    }

    private function check($author, Session $s): array
    {
        return $this->actingAs($author, 'api')->getJson($this->url($s, 'publish-check'))->assertOk()->json('data');
    }

    /** Edits the current version's document in place (a direct author edit). */
    private function edit(Session $s, callable $change): void
    {
        $version = Version::query()->findOrFail($s->current_version_id);
        $version->document = $change($version->document);
        $version->save();
    }

    public function testNothingToPublishBeforeTheApply(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);

        $codes = array_column($this->check($author, $session)['blocking'], 'code');
        $this->assertContains('not_applied', $codes);
        $this->actingAs($author, 'api')->postJson($this->url($session, 'publish'))->assertStatus(409);
    }

    public function testAnAppliedCourseWithoutProblemsIsReady(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);

        $check = $this->check($author, $session);
        $this->assertSame([], $check['blocking']);
        $this->assertTrue($check['facts']['landingValid']);
        $this->assertSame('Free', $check['facts']['price']['label']);
        $this->assertGreaterThan(0, $check['facts']['counts']['lessons']);
        $this->actingAs($author, 'api')->postJson($this->url($session, 'publish'), ['acknowledgedWarnings' => true])->assertOk();
    }

    public function testPublishActivatesTheLandingPage(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $slug = "course-{$session->course_id}";
        $this->assertFalse((bool) Page::query()->where('slug', $slug)->value('active'));

        $this->actingAs($author, 'api')->postJson($this->url($session, 'publish'), ['acknowledgedWarnings' => true])->assertOk();

        $this->assertTrue((bool) Page::query()->where('slug', $slug)->value('active'));
        $this->getJson("/api/pages/{$slug}")->assertOk()->assertJsonPath('data.slug', $slug);
    }

    public function testAPaidCourseWithoutAPriceBlocksPublishing(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->putJson($this->url($session, 'brief'), ['brief' => ['pricing' => ['mode' => 'paid']]])->assertOk();

        $this->assertContains('price_unconfirmed', array_column($this->check($author, $session)['blocking'], 'code'));
        $this->actingAs($author, 'api')->postJson($this->url($session, 'publish'), ['acknowledgedWarnings' => true])->assertStatus(409)
            ->assertJsonPath('data.blocking.0.code', 'price_unconfirmed');
        $this->assertNotSame('published', \Ulams\Courses\Models\Course::query()->find($session->course_id)->status);
    }

    public function testWarningsNeedAcknowledgement(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->putJson($this->url($session, 'brief'), ['brief' => ['theme' => ['preset' => 'coffee', 'accent' => '#f4efe6']]])->assertOk();

        $check = $this->check($author, $session);
        $this->assertContains('accent_adjusted', array_column($check['warnings'], 'code'));
        $this->assertNotNull($check['facts']['theme']['adjustedAccent']);
        $this->actingAs($author, 'api')->postJson($this->url($session, 'publish'))->assertStatus(409)->assertJsonPath('data.blocking', []);
        $this->actingAs($author, 'api')->postJson($this->url($session, 'publish'), ['acknowledgedWarnings' => true])->assertOk();
    }

    public function testAnAccessibilityProblemInGeneratedMarkdownIsAWarning(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->edit($session, function (array $doc) {
            $doc['modules'][0]['lessons'][0]['blocks'][0]['markdown'] = "#### Skipped\n\n[](https://example.test) ![](https://example.test/a.png)\n\n| a | b |\n| 1 | 2 |";

            return $doc;
        });

        $messages = implode(' ', array_column(array_filter($this->check($author, $session)['warnings'], fn ($w) => $w['code'] === 'accessibility'), 'message'));
        $this->assertStringContainsString('heading level jumps', $messages);
        $this->assertStringContainsString('link has no text', $messages);
        $this->assertStringContainsString('no alternative text', $messages);
        $this->assertStringContainsString('no header row', $messages);
    }

    public function testAnEditedLandingIsInvalidAndBlocks(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        Page::query()->where('slug', "course-{$session->course_id}")->update(['content' => json_encode(['component' => 'Page', 'props' => [], 'children' => [['component' => 'Nope', 'props' => []]]])]);

        $codes = array_column($this->check($author, $session)['blocking'], 'code');
        $this->assertContains('landing_invalid', $codes);
    }

    public function testAnotherAuthorCannotRunTheCheck(): void
    {
        $session = $this->toApplied($this->author());
        $this->actingAs($this->author(), 'api')->getJson($this->url($session, 'publish-check'))->assertForbidden();
    }

    public function testTheGeneratedLandingValidatesAgainstThePageCatalogue(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $landing = $session->currentVersion->document['pages']['landing'];

        $this->assertSame([], app(LandingValidator::class)->validate($landing));
        $this->assertNotSame([], app(LandingValidator::class)->validate(['component' => 'Hero', 'props' => []]));
        $this->assertNotSame([], app(LandingValidator::class)->validate(null));
    }

    public function testMarkdownAccessibilityChecks(): void
    {
        $this->assertSame([], MarkdownAccessibility::check("## A\n\n### B\n\n[text](https://x.test) ![alt](https://x.test/a.png)\n\n| a | b |\n|---|---|\n| 1 | 2 |\n\n```\n#### code\n```"));
        $this->assertCount(1, MarkdownAccessibility::check("#### Deep"));
    }

    public function testAccentAdjustmentMatchesTheFront(): void
    {
        $this->assertSame(['value' => '#a84521', 'adjusted' => false], AccentContrast::adjust('#a84521', 'coffee'));
        $this->assertTrue(AccentContrast::adjust('#f4efe6', 'coffee')['adjusted']);
        $this->assertNull(AccentContrast::adjust('red', 'coffee'));
    }
}
