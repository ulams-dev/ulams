<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * Replays the recorded real-model run of the coffee fixture (`course-builder:eval --live --record`,
 * cassettes in tests/cassettes) in strict mode: no synthetic answers, so a prompt or schema change
 * without new cassettes fails here with the missing cassette path.
 */
class CassetteReplayTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ai.fake.mode', 'cassette');
        // the recorded run predates the model critics (ADR 0051); the deterministic ones still run
        $app['config']->set('course_builder.quality.llm_critics', false);
    }

    public function testRecordedRunReplaysEndToEnd(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);
        $this->assertSame(Session::INTERVIEWING, $session->status, (string) $session->runs()->latest()->value('error'));

        $this->action($author, $session, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 30, 'lessonMinutes' => 10]])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'assessments', 'value' => ['quiz', 'final']])->assertStatus(202);
        $this->action($author, $session, 'decide_for_me', 'interview')->assertStatus(202);
        $session->refresh();
        $this->assertSame(Session::OUTLINE_REVIEW, $session->status, (string) $session->runs()->latest()->value('error'));

        $outline = $session->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);
        $session->refresh();
        $this->assertSame(Session::APPLY_REVIEW, $session->status);

        $doc = $session->currentVersion->document;
        $this->assertSame([], app(SchemaRegistry::class)->validateBlueprint($doc));
        $known = array_fill_keys($session->fragmentIds(), true);
        $this->assertSame([], array_values(array_filter(Checks::blueprint($doc, $known), fn ($e) => !str_starts_with($e, 'warning'))));
        $calls = AiCall::query()->forSubject(Session::SUBJECT_TYPE, $session->id)->get();
        $this->assertTrue($calls->every(fn ($c) => str_starts_with((string) $c->request_id, 'cassette:')), 'every call came from a cassette: ' . $calls->reject(fn ($c) => str_starts_with((string) $c->request_id, 'cassette:'))->pluck('task')->implode(','));
        $this->assertSame('claude-sonnet-5-5', $calls->firstWhere('task', 'outline')->model_served);
        $this->assertGreaterThan(0, $calls->where('task', 'lesson')->sum('cache_read_tokens'));

        // the recorded chat edit replays too
        $question = null;
        foreach (Blueprint::lessons($doc) as $item) {
            $question ??= $item['lesson']['quiz']['questions'][0] ?? null;
        }
        $this->actingAs($author, 'api')->postJson("/api/admin/course-builder/sessions/{$session->id}/runs", [
            'messages' => [['role' => 'user', 'content' => 'Make the wrong answers less obvious.']],
            'forwardedProps' => ['selection' => ['elementId' => $question['id']]],
        ])->assertStatus(202);
        $patch = Version::query()->where('session_id', $session->id)->where('kind', 'patch')->first();
        $this->assertNotNull($patch, (string) $session->runs()->latest()->value('error'));
        $this->assertSame(array_column($question['options'], 'id'), array_column(Blueprint::find($patch->document, $question['id'])['node']['options'], 'id'));
    }
}
