<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;

/** L2-14: direct edits of the course structure, each an approved author version and a re-apply. */
class OutlineEditorTest extends TestCase
{
    private function edit(Session $session, array $body)
    {
        return $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/outline", $body);
    }

    private function doc(Session $session): array
    {
        return $session->refresh()->currentVersion->document;
    }

    private function applied(): Session
    {
        $session = $this->toApplied($this->author());
        $this->assertGreaterThanOrEqual(2, count($session->currentVersion->document['modules'][0]['lessons']), 'the fixture has a module with two lessons');

        return $session;
    }

    public function testRenameIsAnAuthorVersionAndReachesTheCourse(): void
    {
        $session = $this->applied();
        $lesson = $this->doc($session)['modules'][0]['lessons'][0];

        $this->edit($session, ['action' => 'rename', 'id' => $lesson['id'], 'title' => 'A better title'])->assertOk();

        $session->refresh();
        $version = $session->currentVersion;
        $this->assertSame('author', $version->kind);
        $this->assertSame('author', $version->origin);
        $this->assertSame(Version::APPROVED, $version->status);
        $this->assertSame('A better title', $this->doc($session)['modules'][0]['lessons'][0]['title']);
        $topicId = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'topic')->where('element_id', $lesson['id'])->value('entity_id');
        $this->assertSame('A better title', Topic::query()->find($topicId)->title);
        $this->assertSame($version->id, $session->applied_version_id);
    }

    public function testMovingALessonUpAndAcrossModulesKeepsItsContent(): void
    {
        $session = $this->applied();
        $doc = $this->doc($session);
        $second = $doc['modules'][0]['lessons'][1];

        $this->edit($session, ['action' => 'move', 'id' => $second['id'], 'index' => 0])->assertOk();
        $moved = $this->doc($session);
        $this->assertSame($second['id'], $moved['modules'][0]['lessons'][0]['id']);
        $this->assertSame($second['blocks'], $moved['modules'][0]['lessons'][0]['blocks'], 'blocks and citations travel with the lesson');
        $this->assertSame($second['objectives'], $moved['modules'][0]['lessons'][0]['objectives']);

        if (count($moved['modules']) > 1) {
            $target = $moved['modules'][1];
            $this->edit($session, ['action' => 'move', 'id' => $second['id'], 'moduleId' => $target['id'], 'index' => 0])->assertOk();
            $after = $this->doc($session);
            $this->assertSame($second['id'], $after['modules'][1]['lessons'][0]['id']);
            $topicId = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'topic')->where('element_id', $second['id'])->value('entity_id');
            $lmsLesson = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'lesson')->where('element_id', $target['id'])->value('entity_id');
            $this->assertSame((int) $lmsLesson, (int) Topic::query()->find($topicId)->lesson_id, 'the topic moved to the other LMS lesson');
        }
    }

    public function testModulesReorder(): void
    {
        $session = $this->applied();
        $doc = $this->doc($session);
        if (count($doc['modules']) < 2) {
            $this->markTestSkipped('needs two modules');
        }
        $this->edit($session, ['action' => 'move', 'id' => $doc['modules'][1]['id'], 'index' => 0])->assertOk();
        $this->assertSame($doc['modules'][1]['id'], $this->doc($session)['modules'][0]['id']);
    }

    public function testAddingALessonNeedsAnObjectiveAndASourceSection(): void
    {
        $session = $this->applied();
        $doc = $this->doc($session);
        $module = $doc['modules'][0];
        $fragment = $session->fragmentIds()[0];

        $this->edit($session, ['action' => 'add', 'kind' => 'lesson', 'moduleId' => $module['id'], 'title' => 'New lesson', 'objective' => 'Name the parts', 'citations' => ['frg_zzzzzzzzzzzz']])->assertStatus(422);
        $this->edit($session, ['action' => 'add', 'kind' => 'lesson', 'moduleId' => $module['id'], 'title' => 'New lesson', 'objective' => '', 'citations' => [$fragment]])->assertStatus(422);
        $this->edit($session, ['action' => 'add', 'kind' => 'lesson', 'moduleId' => $module['id'], 'title' => 'New lesson', 'objective' => 'Name the parts', 'citations' => [$fragment]])->assertOk();

        $lessons = $this->doc($session)['modules'][0]['lessons'];
        $added = end($lessons);
        $this->assertSame('New lesson', $added['title']);
        $this->assertSame('planned', $added['status']);
        $this->assertSame([$fragment], $added['citations']);
        $this->assertTrue(Blueprint::isId($added['id']) && Blueprint::isId($added['objectives'][0]['id']));
    }

    public function testRemoveConfirmsNothingButKeepsAModuleNonEmpty(): void
    {
        $session = $this->applied();
        $doc = $this->doc($session);
        $first = $doc['modules'][0]['lessons'][0];
        $topicId = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'topic')->where('element_id', $first['id'])->value('entity_id');

        $this->edit($session, ['action' => 'remove', 'id' => $first['id']])->assertOk();
        $this->assertNull(Topic::query()->find($topicId), 'the topic is deleted with the lesson');

        // down to the last lesson of the last module: refused
        $remaining = $this->doc($session);
        foreach ($remaining['modules'] as $module) {
            foreach (array_slice($module['lessons'], 1) as $lesson) {
                $this->edit($session, ['action' => 'remove', 'id' => $lesson['id']])->assertOk();
            }
        }
        $left = $this->doc($session);
        while (count($left['modules']) > 1) {
            $this->edit($session, ['action' => 'remove', 'id' => $left['modules'][0]['id']])->assertOk();
            $left = $this->doc($session);
        }
        $this->edit($session, ['action' => 'remove', 'id' => $left['modules'][0]['lessons'][0]['id']])->assertStatus(422);
        $this->edit($session, ['action' => 'remove', 'id' => $left['modules'][0]['id']])->assertStatus(422);
    }

    public function testRenamesAreValidatedAndUnknownElementsRefused(): void
    {
        $session = $this->applied();
        $id = $this->doc($session)['modules'][0]['id'];
        $this->edit($session, ['action' => 'rename', 'id' => $id, 'title' => '<b>x</b>'])->assertStatus(422);
        $this->edit($session, ['action' => 'rename', 'id' => $id, 'title' => 'x'])->assertStatus(422);
        $this->edit($session, ['action' => 'rename', 'id' => '01zzzzzzzzzzzzzzzzzzzzzzzz', 'title' => 'Fine title'])->assertStatus(422);
        $this->edit($session, ['action' => 'explode'])->assertStatus(422);
    }

    public function testOnlyTheAuthorEditsAndOnlyApprovedContent(): void
    {
        $session = $this->applied();
        $id = $this->doc($session)['modules'][0]['id'];
        $this->actingAs($this->tutor(), 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/outline", ['action' => 'rename', 'id' => $id, 'title' => 'Hijack'])->assertStatus(403);

        $fresh = $this->toOutline($this->author());
        $this->edit($fresh, ['action' => 'rename', 'id' => 'x', 'title' => 'Nope'])->assertStatus(409);
    }

    public function testSetFormatOnlyBeforeALessonIsWritten(): void
    {
        $session = $this->applied();
        $lesson = $this->doc($session)['modules'][0]['lessons'][0];
        $this->edit($session, ['action' => 'set_format', 'id' => $lesson['id'], 'contentType' => 'liascript'])->assertStatus(422);
        $this->edit($session, ['action' => 'set_format', 'id' => $lesson['id'], 'contentType' => 'richtext'])->assertOk();
    }
}
