<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;

/** ADR 0192, CV-1: releases are listed per course (api/admin/course-versions), read-only, for the author and admins. */
class ReleaseApiTest extends TestCase
{
    private const API = '/api/admin/course-versions';

    /** @return Release[] oldest first */
    private function releases(Session $session): array
    {
        return Release::query()->where('session_id', $session->id)->orderBy('number')->get()->all();
    }

    private function rename(Session $session, string $title): void
    {
        $lesson = $session->refresh()->currentVersion->document['modules'][0]['lessons'][0];
        $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/outline", ['action' => 'rename', 'id' => $lesson['id'], 'title' => $title])->assertOk();
    }

    public function testTheListIsNewestFirstAndPaginated(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->rename($session, 'One');
        $this->rename($session, 'Two');
        $url = self::API . "/courses/{$session->course_id}/releases";

        $all = $this->actingAs($author, 'api')->getJson($url)->assertOk();
        $this->assertSame([3, 2, 1], array_column($all->json('data.releases'), 'number'));
        $all->assertJsonPath('data.total', 3)->assertJsonPath('data.sessionId', $session->id)->assertJsonPath('data.courseId', (int) $session->course_id);
        $newest = $all->json('data.releases.0');
        $this->assertSame('author', $newest['trigger']);
        $this->assertSame($author->getKey(), $newest['approvedBy']['id']);
        $this->assertNotSame('', $newest['approvedBy']['name']);
        $this->assertSame(1, $newest['summary']['lessons']['changed']);
        $this->assertArrayHasKey('versionNumber', $newest);

        $page = $this->actingAs($author, 'api')->getJson($url . '?perPage=2&page=2')->assertOk();
        $this->assertSame([1], array_column($page->json('data.releases'), 'number'));
        $page->assertJsonPath('data.total', 3)->assertJsonPath('data.perPage', 2)->assertJsonPath('data.page', 2);
    }

    public function testOneReleaseComesWithItsDocument(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $release = $this->releases($session)[0];

        $response = $this->actingAs($author, 'api')->getJson(self::API . "/releases/{$release->id}")->assertOk();

        $response->assertJsonPath('data.id', $release->id)->assertJsonPath('data.number', 1);
        $this->assertSame(Version::query()->find($release->version_id)->document['course']['id'], $response->json('data.document.course.id'));
    }

    public function testAnAdminCanLookAtAnyCourse(): void
    {
        $session = $this->toApplied($this->author());

        $this->actingAs($this->admin(), 'api')->getJson(self::API . "/courses/{$session->course_id}/releases")->assertOk()->assertJsonPath('data.total', 1);
    }

    public function testListsWorkWithAiSwitchedOff(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);

        $this->actingAs($author, 'api')->getJson(self::API . "/courses/{$session->course_id}/releases")->assertOk();
    }

    /** Author isolation inside a tenant (the cross-tenant walk is TenantIsolationCoverageTest; ids of another tenant never resolve). */
    public function testAnotherAuthorAStudentAndAGuestAreRefused(): void
    {
        $session = $this->toApplied($this->author());
        $release = $this->releases($session)[0];
        $urls = [self::API . "/courses/{$session->course_id}/releases", self::API . "/releases/{$release->id}"];

        foreach ([$this->tutor(), $this->student()] as $user) {
            foreach ($urls as $url) {
                $this->actingAs($user, 'api')->getJson($url)->assertStatus(403);
            }
        }
        $this->app['auth']->forgetGuards();
        foreach ($urls as $url) {
            $this->getJson($url)->assertStatus(401);
        }
    }

    public function testUnknownCoursesAndReleasesAreNotFound(): void
    {
        $author = $this->author();
        $missing = '01aaaaaaaaaaaaaaaaaaaaaaaa';

        $this->actingAs($author, 'api')->getJson(self::API . '/courses/999999/releases')->assertStatus(404)->assertJsonFragment(['message' => 'This course was not made with the Course Builder, so it has no releases. Adopt the course to track its versions.']);
        $this->actingAs($author, 'api')->getJson(self::API . "/releases/{$missing}")->assertStatus(404);
        $this->actingAs($author, 'api')->getJson(self::API . '/releases/not-an-id')->assertStatus(404);
        $this->actingAs($author, 'api')->getJson(self::API . '/courses/abc/releases')->assertStatus(404);
    }

    public function testAReleaseOfAnotherAuthorsCourseNeverShowsThroughMyCourse(): void
    {
        $mine = $this->toApplied($this->author());
        $theirs = $this->toApplied($this->author());
        $theirRelease = $this->releases($theirs)[0];

        $list = $this->actingAs($mine->author, 'api')->getJson(self::API . "/courses/{$mine->course_id}/releases")->assertOk();
        $this->assertNotContains($theirRelease->id, array_column($list->json('data.releases'), 'id'));
        $this->actingAs($mine->author, 'api')->getJson(self::API . "/releases/{$theirRelease->id}")->assertStatus(403);
        $this->actingAs($mine->author, 'api')->getJson(self::API . "/courses/{$theirs->course_id}/releases")->assertStatus(403);
    }
}
