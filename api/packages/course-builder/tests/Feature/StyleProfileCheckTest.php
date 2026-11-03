<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Tests\TestCase;

/** ADR 0196, SG-4: the style check in the publish summary and as an endpoint; a banned phrase blocks only when the profile says so. */
class StyleProfileCheckTest extends TestCase
{
    private const P = '/api/admin/course-builder/style-profiles';

    /** A profile read from the guide, then revised to ban a word the generated lessons use, approved. @return array{id:string,cite:string} */
    private function approvedBanning(string $phrase, bool $blocking): array
    {
        $author = $this->author();
        $admin = $this->admin();
        $guide = $this->actingAs($author, 'api')->post(self::P, ['file' => $this->fixture('style-guide.md')], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $cite = $guide['profile']['banned']['phrases'][0]['citations'][0];
        $this->actingAs($admin, 'api')->postJson(self::P . "/{$guide['id']}/approve")->assertOk();
        $revision = $this->actingAs($author, 'api')->postJson(self::P, ['from' => $guide['id'], 'profile' => ['banned' => ['blocking' => $blocking, 'phrases' => [['phrase' => $phrase, 'citations' => [$cite]]]]]])->assertCreated()->json('data.id');
        $this->actingAs($admin, 'api')->postJson(self::P . "/{$revision}/approve")->assertOk();

        return ['id' => $revision, 'cite' => $cite];
    }

    private function publishCheck($author, Session $session): array
    {
        return $this->actingAs($author, 'api')->getJson("/api/admin/course-builder/sessions/{$session->id}/publish-check")->assertOk()->json('data');
    }

    public function testABannedPhraseBlocksPublishingWithItsCitationWhenTheProfileSaysSo(): void
    {
        $profile = $this->approvedBanning('coffee', true);
        $author = $this->author();
        $session = $this->toApplied($author);

        $check = $this->publishCheck($author, $session);
        $banned = collect($check['blocking'])->firstWhere('code', 'style_banned');
        $this->assertNotNull($banned, json_encode($check['blocking']));
        $this->assertSame('coffee', $banned['term']);
        $this->assertSame($profile['cite'], $banned['citations'][0]['id']);
        $this->assertSame('§4 Banned phrases', $banned['citations'][0]['label']);
        $this->assertStringContainsString('is a banned phrase of the style guide (style guide §4 Banned phrases)', $banned['message']);
        $this->assertNotEmpty($banned['elementId']);
        $this->assertSame($profile['id'], $check['facts']['style']['profile']['id']);

        $response = $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/publish", ['acknowledgedWarnings' => true])->assertStatus(409);
        $this->assertStringContainsString('banned phrase', $response->json('message'));
        $this->assertFalse((bool) $session->refresh()->stateValue('published', false));
    }

    public function testWithoutTheBlockingSwitchTheSamePhraseOnlyWarnsAndPublishingNeedsAcknowledgement(): void
    {
        $this->approvedBanning('coffee', false);
        $author = $this->author();
        $session = $this->toApplied($author);

        $check = $this->publishCheck($author, $session);
        $this->assertSame([], array_column($check['blocking'], 'code'));
        $this->assertContains('style_banned_warning', array_column($check['warnings'], 'code'));
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/publish")->assertStatus(409);
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/publish", ['acknowledgedWarnings' => true])->assertOk();
    }

    public function testAProfileThatMatchesNothingAddsNoFindingsAndNoProfileMeansNoStyleFacts(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->assertNull($this->publishCheck($author, $session)['facts']['style']);

        $this->approvedBanning('zzzzqqq', true);
        $check = $this->publishCheck($author, $session);
        $this->assertSame([], array_column($check['blocking'], 'code'));
        $this->assertSame(0, $check['facts']['style']['blocking']);
        $this->assertIsArray($check['facts']['style']['hints']);
    }

    public function testTheCheckEndpointReportsAgainstAnyProfileEvenASuggestionAndChangesNothing(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $draft = $this->actingAs($author, 'api')->postJson(self::P, ['name' => 'Draft', 'profile' => ['banned' => ['blocking' => true, 'phrases' => [['phrase' => 'coffee', 'citations' => []]]]]])->assertCreated()->json('data.id');
        $before = $session->refresh()->updated_at;

        $report = $this->actingAs($author, 'api')->getJson(self::P . "/{$draft}/check?course={$session->course_id}")->assertOk()->json('data');
        $this->assertSame('suggested', $report['profile']['status']);
        $this->assertSame($session->course_id, $report['course']['id']);
        $this->assertTrue($report['blocksPublishing']);
        $this->assertSame($report['counts']['blocking'], count($report['blocking']));
        $this->assertNotEmpty($report['blocking']);
        $this->assertEquals($before, $session->refresh()->updated_at);
    }

    public function testTheCheckEndpointIsolation(): void
    {
        $owner = $this->author();
        $session = $this->toApplied($owner);
        $profile = $this->actingAs($owner, 'api')->postJson(self::P, ['name' => 'Mine', 'profile' => ['voice' => ['person' => 'you', 'citations' => []]]])->assertCreated()->json('data.id');
        $uri = self::P . "/{$profile}/check?course={$session->course_id}";

        $this->actingAs($this->tutor(), 'api')->getJson($uri)->assertForbidden();
        $this->actingAs($this->student(), 'api')->getJson($uri)->assertForbidden();
        $this->actingAs($this->admin(), 'api')->getJson($uri)->assertOk();
        $this->actingAs($owner, 'api')->getJson($uri)->assertOk();
        $this->actingAs($owner, 'api')->getJson(self::P . "/{$profile}/check")->assertStatus(422);
        $this->actingAs($owner, 'api')->getJson(self::P . "/{$profile}/check?course=999999")->assertNotFound();
        $this->actingAs($owner, 'api')->getJson(self::P . '/01aaaaaaaaaaaaaaaaaaaaaaaa/check?course=1')->assertNotFound();
        $this->actingAs($owner, 'api')->getJson(self::P . '/not-an-id/check?course=1')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson($uri)->assertStatus(401);
    }
}
