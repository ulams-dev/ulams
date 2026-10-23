<?php

namespace Ulams\CourseVariants\Tests\Support;

use Ulams\CourseVariants\Contracts\PlacementSource;

/** Two single-answer questions per level, cited and tied to an objective. */
final class FakePlacementSource implements PlacementSource
{
    public function __construct(private readonly int $perLevel = 3)
    {
    }

    public function questions(array $courses): array
    {
        $out = [];
        foreach ($courses as $course) {
            for ($i = 1; $i <= $this->perLevel; $i++) {
                $id = sprintf('%s%02d', substr($course['level'], 0, 1), $i);
                $out[] = ['level' => $course['level'], 'courseId' => $course['courseId'], 'elementId' => "q-{$id}", 'question' => [
                    'type' => 'single', 'stem' => "Question {$id}?", 'explanation' => 'Because.', 'citations' => ['frg_' . $id], 'objectiveIds' => ["o{$i}"],
                    'options' => [['id' => 'a', 'text' => 'Right', 'correct' => true], ['id' => 'b', 'text' => 'Wrong', 'correct' => false]],
                ]];
            }
        }
        $out[] = ['level' => 'expert', 'courseId' => 0, 'elementId' => 'uncited', 'question' => ['type' => 'single', 'stem' => 'No citation', 'citations' => [], 'options' => [['id' => 'a', 'text' => 'x', 'correct' => true], ['id' => 'b', 'text' => 'y', 'correct' => false]]]];
        $out[] = ['level' => 'expert', 'courseId' => 0, 'elementId' => 'short', 'question' => ['type' => 'short', 'stem' => 'Free text', 'citations' => ['frg_x'], 'options' => [['id' => 'a', 'text' => 'x', 'correct' => true]]]];

        return $out;
    }
}
