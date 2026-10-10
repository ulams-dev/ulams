<?php

namespace Ulams\CourseBuilder\Sources;

use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;

/**
 * The reverse of citations: for every source of a session, its sections (fragments) with the
 * elements of the current blueprint that cite them. Authors use it to see what the course covers,
 * which sections no element draws on ("uncovered") and, from a selected element, which sections
 * it rests on. Built from the document only; nothing is stored.
 */
final class CitationIndex
{
    /**
     * @return array{versionId:?string,sources:array<int,array<string,mixed>>,elements:array<string,array<int,string>>}
     */
    public function build(Session $session): array
    {
        $version = $session->currentVersion;
        $citedBy = [];
        $elements = [];
        if ($version !== null) {
            foreach ($this->elements($version->document) as $element) {
                foreach ($element['citations'] as $fragmentId) {
                    $citedBy[$fragmentId][$element['id']] = ['id' => $element['id'], 'target' => $element['target'], 'type' => $element['type'], 'label' => $element['label']];
                    $elements[$element['id']][] = $fragmentId;
                }
            }
        }

        $sources = [];
        foreach ($session->sources()->where('status', 'ready')->orderBy('created_at')->get() as $source) {
            $sections = [];
            $uncovered = 0;
            foreach (Fragment::query()->where('source_id', $source->id)->orderBy('ordinal')->get() as $fragment) {
                $cited = array_values($citedBy[$fragment->id] ?? []);
                $uncovered += $cited === [] ? 1 : 0;
                $sections[] = [
                    'fragmentId' => $fragment->id,
                    'label' => $fragment->label(),
                    'level' => max(1, (int) $fragment->level),
                    'tokens' => (int) $fragment->token_estimate,
                    'citedBy' => array_slice($cited, 0, 50),
                    'citedByCount' => count($cited),
                ];
            }
            $sources[] = [
                'id' => $source->id,
                'name' => (string) ($source->metadata['title'] ?? $source->original_name),
                'sections' => $sections,
                'total' => count($sections),
                'uncovered' => $uncovered,
            ];
        }

        return ['versionId' => $version?->id, 'sources' => $sources, 'elements' => array_map(fn (array $ids) => array_values(array_unique($ids)), $elements)];
    }

    /**
     * Elements that carry citations: lessons, blocks, questions and objectives. An objective points
     * (`target`) to its lesson, because the workspace selects lessons, blocks and questions.
     *
     * @return \Generator<int,array{id:string,target:string,type:string,label:string,citations:string[]}>
     */
    private function elements(array $doc): \Generator
    {
        foreach ($doc['course']['objectives'] ?? [] as $i => $objective) {
            yield ['id' => $objective['id'], 'target' => $doc['course']['id'], 'type' => 'objective', 'label' => 'Course objective ' . ($i + 1), 'citations' => $objective['citations'] ?? []];
        }
        foreach ($doc['modules'] ?? [] as $m => $module) {
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                $label = 'Lesson ' . ($m + 1) . '.' . ($l + 1);
                yield ['id' => $lesson['id'], 'target' => $lesson['id'], 'type' => 'lesson', 'label' => $label, 'citations' => $lesson['citations'] ?? []];
                foreach ($lesson['objectives'] ?? [] as $o => $objective) {
                    yield ['id' => $objective['id'], 'target' => $lesson['id'], 'type' => 'objective', 'label' => "{$label} › objective " . ($o + 1), 'citations' => $objective['citations'] ?? []];
                }
                foreach ($lesson['blocks'] ?? [] as $b => $block) {
                    yield ['id' => $block['id'], 'target' => $block['id'], 'type' => 'block', 'label' => "{$label} › block " . ($b + 1), 'citations' => $block['citations'] ?? []];
                }
                foreach ($lesson['quiz']['questions'] ?? [] as $q => $question) {
                    yield ['id' => $question['id'], 'target' => $question['id'], 'type' => 'question', 'label' => "{$label} › Q" . ($q + 1), 'citations' => $question['citations'] ?? []];
                }
            }
        }
        foreach ($doc['finalTest']['questions'] ?? [] as $q => $question) {
            yield ['id' => $question['id'], 'target' => $question['id'], 'type' => 'question', 'label' => 'Final test › Q' . ($q + 1), 'citations' => $question['citations'] ?? []];
        }
    }
}
