<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\BlueprintDiff;
use Ulams\CourseBuilder\Tests\TestCase;

/** The element diff covers every element a lesson can hold, including self-checks and interactions. */
class BlueprintDiffTest extends TestCase
{
    public function testBlueprintDiffNowSeesSelfChecksAndInteractions(): void
    {
        $session = $this->toApplied($this->author());
        $doc = $session->currentVersion->document;
        $lesson = &$doc['modules'][0]['lessons'][0];
        $q = fn (string $stem) => ['id' => Blueprint::newId(), 'type' => 'truefalse', 'stem' => $stem, 'options' => [['id' => Blueprint::newId(), 'text' => 'True', 'correct' => true], ['id' => Blueprint::newId(), 'text' => 'False', 'correct' => false]], 'explanation' => 'Because.', 'citations' => $lesson['citations'], 'objectiveIds' => []];
        $lesson['selfChecks'] = [$q('Is the water hot?')];
        $lesson['interaction'] = ['id' => Blueprint::newId(), 'kind' => 'h5p', 'library' => 'H5P.Blanks', 'title' => 'Fill in', 'data' => ['instruction' => 'Fill in the gaps'], 'citations' => $lesson['citations'], 'objectiveIds' => []];
        unset($lesson);
        $withBoth = $doc;

        $added = collect(BlueprintDiff::compare($session->currentVersion->document, $withBoth))->keyBy('type');
        $this->assertSame('added', $added['interaction']['kind']);
        $this->assertSame('Lesson 1.1 › activity', $added['interaction']['label']);
        $this->assertContains('question', array_column(BlueprintDiff::compare($session->currentVersion->document, $withBoth), 'type'));

        $edited = $withBoth;
        $edited['modules'][0]['lessons'][0]['selfChecks'][0]['stem'] = 'Is the water cold?';
        $edited['modules'][0]['lessons'][0]['selfChecks'][0]['options'][0]['correct'] = false;
        $edited['modules'][0]['lessons'][0]['interaction']['data']['instruction'] = 'Fill in all the gaps';
        $edited['modules'][0]['lessons'][0]['interaction']['title'] = 'Fill in again';
        $changes = collect(BlueprintDiff::compare($withBoth, $edited))->keyBy(fn ($c) => $c['type'] . ':' . $c['id']);
        $this->assertCount(3, $changes, 'the self-check, its option and the interaction');
        $interaction = $changes->first(fn ($c) => $c['type'] === 'interaction');
        $this->assertEqualsCanonicalizing(['title', 'data'], array_column($interaction['fields'], 'field'));
        $check = $changes->first(fn ($c) => $c['type'] === 'question');
        $this->assertSame(['stem'], array_column($check['fields'], 'field'));
        $this->assertContains('option', array_column($changes->all(), 'type'));

        $removed = $edited;
        $removed['modules'][0]['lessons'][0]['selfChecks'] = [];
        $removed['modules'][0]['lessons'][0]['interaction'] = null;
        $gone = BlueprintDiff::compare($edited, $removed);
        $this->assertSame(['removed'], array_values(array_unique(array_column($gone, 'kind'))));
        $this->assertContains('interaction', array_column($gone, 'type'));
        $this->assertSame([], BlueprintDiff::compare($edited, $edited));
    }
}
