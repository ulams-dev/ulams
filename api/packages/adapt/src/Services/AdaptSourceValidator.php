<?php

namespace Ulams\Adapt\Services;

/**
 * Structural validation of an Adapt course source, done in the API (no GPL code): the six parts,
 * unique `_id`s, parent links down the hierarchy course → contentObjects (menus/pages) → articles →
 * blocks → components, and component types from the allow-list of core plugins. Plugin-specific
 * properties are validated by the build worker, which carries the plugins' schemas.
 */
class AdaptSourceValidator
{
    /**
     * @return string[] problems, each with a JSON-pointer-like path; empty when valid
     */
    public function validate(mixed $source): array
    {
        if (!is_array($source)) {
            return ['/: the source must be a JSON object'];
        }

        $errors = [];
        foreach (['course', 'config'] as $key) {
            if (!isset($source[$key]) || !is_array($source[$key]) || array_is_list($source[$key])) {
                $errors[] = "/{$key}: required object";
            }
        }
        foreach (['contentObjects', 'articles', 'blocks', 'components'] as $key) {
            if (!isset($source[$key]) || !is_array($source[$key]) || !array_is_list($source[$key])) {
                $errors[] = "/{$key}: required array";
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        $courseId = $source['course']['_id'] ?? null;
        if (!is_string($courseId) || $courseId === '') {
            $errors[] = '/course/_id: required string';
        }
        if (!is_string($source['course']['title'] ?? null) || trim($source['course']['title']) === '') {
            $errors[] = '/course/title: required string';
        }

        $ids = is_string($courseId) ? [$courseId => 'course'] : [];
        $levels = ['contentObjects', 'articles', 'blocks', 'components'];
        foreach ($levels as $level) {
            foreach ($source[$level] as $i => $item) {
                $id = is_array($item) ? ($item['_id'] ?? null) : null;
                if (!is_string($id) || $id === '') {
                    $errors[] = "/{$level}/{$i}/_id: required string";
                    continue;
                }
                if (isset($ids[$id])) {
                    $errors[] = "/{$level}/{$i}/_id: duplicate id {$id}";
                    continue;
                }
                $ids[$id] = $level;
            }
        }

        // allowed parent level for each level
        $parents = [
            'contentObjects' => ['course', 'contentObjects'],
            'articles' => ['contentObjects'],
            'blocks' => ['articles'],
            'components' => ['blocks'],
        ];
        $pageIds = [];
        foreach ($source['contentObjects'] as $item) {
            if (is_array($item) && ($item['_type'] ?? null) === 'page' && is_string($item['_id'] ?? null)) {
                $pageIds[$item['_id']] = true;
            }
        }
        $components = (array) config('ulams_adapt.components', []);

        foreach ($levels as $level) {
            foreach ($source[$level] as $i => $item) {
                if (!is_array($item) || !is_string($item['_id'] ?? null)) {
                    continue;
                }
                $parent = $item['_parentId'] ?? null;
                if (!is_string($parent) || !isset($ids[$parent]) || !in_array($ids[$parent], $parents[$level], true)) {
                    $errors[] = "/{$level}/{$i}/_parentId: must reference " . implode(' or ', $parents[$level]);
                } elseif ($level === 'articles' && !isset($pageIds[$parent])) {
                    $errors[] = "/{$level}/{$i}/_parentId: articles belong to a contentObject of _type page";
                }
                if ($level === 'contentObjects' && !in_array($item['_type'] ?? null, ['menu', 'page'], true)) {
                    $errors[] = "/{$level}/{$i}/_type: must be menu or page";
                }
                if ($level === 'components' && !in_array($item['_component'] ?? null, $components, true)) {
                    $errors[] = "/{$level}/{$i}/_component: not an allowed component (" . implode(', ', $components) . ')';
                }
            }
        }

        return $errors;
    }
}
