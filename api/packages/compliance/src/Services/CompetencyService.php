<?php

namespace Ulams\Compliance\Services;

use Illuminate\Validation\ValidationException;
use Ulams\Compliance\Models\Competency;

class CompetencyService
{
    /** @return list<array<string, mixed>> the framework as a tree */
    public function tree(): array
    {
        $all = Competency::query()->orderBy('name')->get();
        $byParent = $all->groupBy(fn (Competency $c) => $c->parent_id ?? 0);
        $build = function (int $parent) use (&$build, $byParent): array {
            return ($byParent[$parent] ?? collect())->map(fn (Competency $c) => $this->present($c) + ['children' => $build($c->getKey())])->values()->all();
        };

        return $build(0);
    }

    public function save(array $data, ?Competency $competency = null): Competency
    {
        $competency ??= new Competency();
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->assertNoCycle($competency, (int) $data['parent_id']);
        }
        $competency->fill(array_intersect_key($data, array_flip(['name', 'code', 'description', 'parent_id'])))->save();

        return $competency;
    }

    /** A competency cannot move under itself or under one of its own descendants. */
    private function assertNoCycle(Competency $competency, int $parentId): void
    {
        if (!$competency->exists) {
            return;
        }
        for ($id = $parentId, $guard = 0; $id !== null && $guard < 100; $guard++) {
            if ($id === $competency->getKey()) {
                throw ValidationException::withMessages(['parent_id' => 'A competency cannot be placed under itself or one of its sub-competencies.']);
            }
            $id = Competency::query()->whereKey($id)->value('parent_id');
        }
    }

    /** @return array<string, mixed> */
    public function present(Competency $competency): array
    {
        return ['id' => $competency->getKey(), 'parent_id' => $competency->parent_id, 'name' => $competency->name, 'code' => $competency->code, 'description' => $competency->description];
    }
}
