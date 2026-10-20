<?php

namespace Ulams\Compliance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ulams\Compliance\Models\LearningPath;

class PathService
{
    /**
     * Saves a path with its steps (replaced as a whole). Each step is `{course_id, prerequisites?: course ids}`;
     * prerequisites must be other courses of the same path and must not form a cycle.
     *
     * @param array{title: string, description?: string|null, steps?: list<array{course_id: int, prerequisites?: list<int>}>} $data
     */
    public function save(array $data, ?LearningPath $path = null): LearningPath
    {
        $steps = $data['steps'] ?? null;
        if ($steps !== null) {
            $this->validateSteps($steps);
        }

        return DB::transaction(function () use ($data, $path, $steps) {
            $path ??= new LearningPath();
            $path->fill(array_intersect_key($data, array_flip(['title', 'description'])))->save();
            if ($steps !== null) {
                $path->steps()->delete();
                foreach (array_values($steps) as $position => $step) {
                    $path->steps()->create([
                        'course_id' => $step['course_id'],
                        'position' => $position,
                        'prerequisites' => array_values(array_unique(array_map('intval', $step['prerequisites'] ?? []))) ?: null,
                    ]);
                }
            }

            return $path->load('steps.course');
        });
    }

    /** @param list<array{course_id: int, prerequisites?: list<int>}> $steps */
    public function validateSteps(array $steps): void
    {
        $ids = array_map(fn (array $step) => (int) $step['course_id'], $steps);
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['steps' => 'A course can appear in a path only once.']);
        }

        $graph = [];
        foreach ($steps as $index => $step) {
            $course = (int) $step['course_id'];
            $prerequisites = array_map('intval', $step['prerequisites'] ?? []);
            foreach ($prerequisites as $prerequisite) {
                if ($prerequisite === $course) {
                    throw ValidationException::withMessages(["steps.{$index}.prerequisites" => 'A course cannot be its own prerequisite.']);
                }
                if (!in_array($prerequisite, $ids, true)) {
                    throw ValidationException::withMessages(["steps.{$index}.prerequisites" => "Prerequisite {$prerequisite} is not a course of this path."]);
                }
            }
            $graph[$course] = $prerequisites;
        }

        if ($this->hasCycle($graph)) {
            throw ValidationException::withMessages(['steps' => 'The prerequisites form a cycle.']);
        }
    }

    /** @param array<int, list<int>> $graph course => prerequisites */
    private function hasCycle(array $graph): bool
    {
        $state = []; // 1 = visiting, 2 = done
        $visit = function (int $node) use (&$visit, &$state, $graph): bool {
            if (($state[$node] ?? 0) === 1) {
                return true;
            }
            if (($state[$node] ?? 0) === 2) {
                return false;
            }
            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $next) {
                if ($visit($next)) {
                    return true;
                }
            }
            $state[$node] = 2;

            return false;
        };
        foreach (array_keys($graph) as $node) {
            if ($visit($node)) {
                return true;
            }
        }

        return false;
    }
}
