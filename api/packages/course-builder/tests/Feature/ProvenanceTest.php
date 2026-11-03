<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Provenance;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Blueprint\SemanticDiff;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;

/** ADR 0194 (AM-2): who is behind the text of an element, from `ai_hash` and `edited_by_author`. */
class ProvenanceTest extends TestCase
{
    private function elements(array $doc): array
    {
        $out = [];
        foreach ($doc['modules'] as $module) {
            $out[] = ['module', $module];
            foreach ($module['lessons'] as $lesson) {
                $out[] = ['lesson', $lesson];
                foreach ($lesson['blocks'] ?? [] as $block) {
                    $out[] = ['block', $block];
                }
                foreach ($lesson['quiz']['questions'] ?? [] as $q) {
                    $out[] = ['question', $q];
                }
            }
        }
        foreach ($doc['finalTest']['questions'] ?? [] as $q) {
            $out[] = ['question', $q];
        }

        return $out;
    }

    private function edit(Session $session, array $body)
    {
        return $this->actingAs($session->author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/outline", $body);
    }

    public function testSchemaAcceptsBothFieldsAndOldDocumentsUnchanged(): void
    {
        $session = $this->toApplyReview($this->author());
        $doc = $session->currentVersion->document;
        $registry = app(SchemaRegistry::class);
        $this->assertSame([], $registry->validateBlueprint($doc));

        $strip = function (array $n) use (&$strip) {
            unset($n['ai_hash'], $n['edited_by_author']);

            return array_map(fn ($v) => is_array($v) ? $strip($v) : $v, $n);
        };
        $this->assertSame([], $registry->validateBlueprint($strip($doc)), 'a document without the fields stays valid');

        $doc['modules'][0]['lessons'][0]['blocks'][0]['edited_by_author'] = 'yes';
        $this->assertNotSame([], $registry->validateBlueprint($doc), 'the flag is a boolean');
        $doc['modules'][0]['lessons'][0]['blocks'][0]['edited_by_author'] = true;
        $doc['modules'][0]['lessons'][0]['blocks'][0]['ai_hash'] = 'not-a-hash';
        $this->assertNotSame([], $registry->validateBlueprint($doc), 'the hash is 64 hex characters');
    }

    public function testTheHashIgnoresIdsKeyOrderAndWhitespaceButNotText(): void
    {
        $a = ['id' => 'x', 'kind' => 'text', 'markdown' => "One\r\ntwo ", 'citations' => ['f1']];
        $b = ['citations' => ['f1'], 'markdown' => "One\ntwo", 'kind' => 'text', 'id' => 'y', 'origin' => 'ai', 'meta' => ['createdBy' => 'z']];
        $this->assertSame(Provenance::hash($a), Provenance::hash($b));
        $this->assertNotSame(Provenance::hash($a), Provenance::hash(['markdown' => 'Other'] + $a));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', Provenance::hash($a));
    }

    public function testClassificationTableHasOneElementPerClass(): void
    {
        $ai = Provenance::stampElement(['id' => 'a', 'kind' => 'text', 'markdown' => 'Original', 'citations' => ['f1']]);
        $changedText = ['markdown' => 'Changed by hand'] + $ai;
        $table = [
            [Provenance::ACCEPTED_UNCHANGED, $ai, false],
            [Provenance::REVISED_ON_REQUEST, $ai, true],
            [Provenance::EDITED_BY_AUTHOR, $changedText, false],
            [Provenance::EDITED_BY_AUTHOR, $ai + ['edited_by_author' => true], false],
            [Provenance::EDITED_BY_AUTHOR, ['origin' => 'author', 'edited_by_author' => true] + $ai, false],
            [Provenance::AUTHOR_WRITTEN, ['id' => 'b', 'kind' => 'text', 'markdown' => 'Mine', 'citations' => [], 'origin' => 'author'], false],
            [Provenance::UNKNOWN, ['id' => 'c', 'kind' => 'text', 'markdown' => 'Before AM-2', 'citations' => ['f1']], false],
        ];
        foreach ($table as $i => [$expected, $element, $patched]) {
            $this->assertSame($expected, Provenance::classify($element, $patched), "row {$i}");
        }
    }

    public function testStampingSkipsWhatAnAuthorWroteOrEdited(): void
    {
        $doc = ['modules' => [['id' => 'm', 'title' => 'M', 'lessons' => [[
            'id' => 'l', 'title' => 'L', 'minutes' => 5, 'blocks' => [
                ['id' => 'b1', 'kind' => 'text', 'markdown' => 'AI', 'citations' => ['f']],
                ['id' => 'b2', 'kind' => 'text', 'markdown' => 'Hand', 'citations' => [], 'origin' => 'author'],
                ['id' => 'b3', 'kind' => 'text', 'markdown' => 'Edited', 'citations' => ['f'], 'edited_by_author' => true],
            ], 'quiz' => null,
        ]]]], 'finalTest' => null];

        $stamped = Provenance::stamp($doc)['modules'][0]['lessons'][0]['blocks'];

        $this->assertArrayHasKey('ai_hash', $stamped[0]);
        $this->assertArrayNotHasKey('ai_hash', $stamped[1]);
        $this->assertArrayNotHasKey('ai_hash', $stamped[2]);
    }

    public function testEveryElementTheAiWritesIsStampedAndReadsAsAcceptedUnchanged(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author, 'coffee-brewing.md', ['quiz', 'final']);

        foreach ($this->elements($session->currentVersion->document) as [$type, $element]) {
            $this->assertArrayHasKey('ai_hash', $element, "{$type} {$element['id']}");
            $this->assertSame(Provenance::ACCEPTED_UNCHANGED, Provenance::classify($element), "{$type} {$element['id']}");
        }
        $outline = Version::query()->where('session_id', $session->id)->where('kind', 'outline')->orderBy('number')->first();
        $this->assertArrayHasKey('ai_hash', $outline->document['modules'][0]['lessons'][0], 'the outline is stamped too');
    }

    public function testEditingInTheOutlineEditorFlagsTheEditedElementOnly(): void
    {
        $session = $this->toApplied($this->author());
        $doc = $session->refresh()->currentVersion->document;
        [$first, $second] = [$doc['modules'][0]['lessons'][0], $doc['modules'][0]['lessons'][1]];

        $this->edit($session, ['action' => 'rename', 'id' => $first['id'], 'title' => 'A better title'])->assertOk();
        $this->edit($session, ['action' => 'move', 'id' => $second['id'], 'index' => 0])->assertOk();

        $after = $session->refresh()->currentVersion->document;
        $byId = [];
        foreach ($after['modules'][0]['lessons'] as $lesson) {
            $byId[$lesson['id']] = $lesson;
        }
        $this->assertTrue($byId[$first['id']]['edited_by_author']);
        $this->assertSame(Provenance::EDITED_BY_AUTHOR, Provenance::classify($byId[$first['id']]));
        $this->assertArrayNotHasKey('edited_by_author', $byId[$second['id']], 'moving a lesson is not an edit of its text');
        $this->assertSame(Provenance::ACCEPTED_UNCHANGED, Provenance::classify($byId[$second['id']]));
        $this->assertSame([], app(SchemaRegistry::class)->validateBlueprint($after));
        // the blocks of the renamed lesson were not touched
        foreach ($byId[$first['id']]['blocks'] as $block) {
            $this->assertSame(Provenance::ACCEPTED_UNCHANGED, Provenance::classify($block));
        }
    }

    public function testAnAddedLessonAndAFormatChangeAreAuthorEdits(): void
    {
        $session = $this->toApplied($this->author());
        $doc = $session->refresh()->currentVersion->document;
        $fragment = $session->fragmentIds()[0];

        $this->edit($session, ['action' => 'add', 'kind' => 'lesson', 'moduleId' => $doc['modules'][0]['id'], 'title' => 'Extra lesson', 'objective' => 'Know the extra thing', 'citations' => [$fragment]])->assertOk();

        $lessons = $session->refresh()->currentVersion->document['modules'][0]['lessons'];
        $this->assertTrue(end($lessons)['edited_by_author']);
    }

    public function testAChatPatchRestampsTheElementAndClearsAnEarlierEdit(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $doc = $session->refresh()->currentVersion->document;
        $question = null;
        foreach (Blueprint::lessons($doc) as $item) {
            if (!empty($item['lesson']['quiz']['questions'])) {
                $question = $item['lesson']['quiz']['questions'][0];
                break;
            }
        }
        $this->assertNotNull($question);
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'threadId' => $session->id, 'runId' => 'r',
            'messages' => [['id' => 'm1', 'role' => 'user', 'content' => 'Make the distractors less obvious']],
            'forwardedProps' => ['selection' => ['elementId' => $question['id']]],
        ])->assertStatus(202);
        $patch = Version::query()->where('session_id', $session->id)->where('kind', 'patch')->latest('number')->firstOrFail();

        $patched = Blueprint::find($patch->document, $question['id'])['node'];

        $this->assertArrayHasKey('ai_hash', $patched);
        $this->assertSame(Provenance::REVISED_ON_REQUEST, Provenance::classify($patched, true), 'the patch version names the element: kind patch, element_id');
        $this->assertSame($question['id'], $patch->element_id);
        $this->assertSame($patched['ai_hash'], Provenance::hash($patched));
    }

    public function testTheSemanticDiffIgnoresTheBookkeeping(): void
    {
        $session = $this->toApplyReview($this->author());
        $doc = $session->currentVersion->document;
        $flagged = Provenance::markEditedIds($doc, [$doc['modules'][0]['lessons'][0]['id']]);
        $rehashed = Provenance::stamp($flagged);

        $semantic = SemanticDiff::compare($doc, $rehashed);
        $this->assertSame([], $semantic['changes']);
        $changes = \Ulams\CourseBuilder\Blueprint\BlueprintDiff::compare($doc, $rehashed);
        $this->assertSame([], $changes, 'ai_hash and edited_by_author never show as changes');
    }
}
