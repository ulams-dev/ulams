<?php

namespace Ulams\KnowledgeExport\Services;

use Ulams\CourseBuilder\Apply\LessonMarkdown;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Contracts\FragmentArchive;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;

/**
 * Where the citations of a course come from: the blueprint that the course builder last applied. Each
 * lesson block cites source fragments; the block's rendered Markdown is what ended up in the topic. A
 * topic is matched to its lesson through the entity map. Courses written by hand have no blueprint and
 * so no blocks, and their chunks are exported as uncited.
 */
final class CitationResolver
{
    public function available(): bool
    {
        return class_exists(Session::class);
    }

    /**
     * Topic id => the blocks of its lesson, in order, as rendered into the topic text.
     *
     * @return array<int,list<array{text:string,citations:list<string>}>>
     */
    public function blocksByTopic(int $courseId): array
    {
        if (!$this->available()) {
            return [];
        }
        $session = Session::query()->where('course_id', $courseId)->whereNotNull('applied_version_id')->orderByDesc('updated_at')->first();
        $version = $session !== null ? Version::query()->find($session->applied_version_id) : null;
        if ($session === null || $version === null) {
            return [];
        }
        $lessons = [];
        foreach (Blueprint::lessons($version->document) as $item) {
            $lessons[$item['lesson']['id']] = $item['lesson'];
        }
        $out = [];
        $entries = EntityMapEntry::query()->where('session_id', $session->id)->where('entity_type', 'topic')->whereNull('retired_at')->get();
        foreach ($entries as $entry) {
            $lesson = $lessons[$entry->element_id] ?? null;
            if ($lesson === null) {
                continue;
            }
            $blocks = [];
            foreach ($lesson['blocks'] ?? [] as $block) {
                $text = LessonMarkdown::sanitise(trim((string) $block['markdown']));
                if (($block['kind'] ?? '') === 'callout') {
                    $text = implode("\n", array_map(fn ($line) => '> ' . $line, explode("\n", $text)));
                }
                $blocks[] = ['text' => $text, 'citations' => array_values(array_unique($block['citations'] ?? []))];
            }
            $out[(int) $entry->entity_id] = $blocks;
        }

        return $out;
    }

    /**
     * @param list<string> $ids fragment ids
     * @return array<string,array{fragment_id:string,label:?string,source_id:?string,source_name:?string,page_start:?int,page_end:?int,content_hash:?string}>
     */
    public function describe(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        $out = [];
        if ($ids === []) {
            return [];
        }
        if (!$this->available()) {
            return $this->unresolved($ids);
        }
        foreach (Fragment::query()->with('source')->whereIn('id', $ids)->get() as $fragment) {
            $source = $fragment->source;
            $out[$fragment->id] = [
                'fragment_id' => $fragment->id,
                'label' => $fragment->label(),
                'source_id' => $source?->id,
                'source_name' => $source !== null ? (string) ($source->metadata['title'] ?? $source->original_name) : null,
                'page_start' => $fragment->page_start,
                'page_end' => $fragment->page_end,
                'content_hash' => $fragment->content_hash,
            ];
        }
        $archive = app(FragmentArchive::class);
        foreach ($ids as $id) {
            if (isset($out[$id])) {
                continue;
            }
            // the source changed since: the archive still knows the fragment the text was written from
            $old = $archive->find($id);
            $out[$id] = $old === null ? $this->unresolved([$id])[$id] : [
                'fragment_id' => $id,
                'label' => $old['label'],
                'source_id' => $old['source']['id'],
                'source_name' => $old['source']['name'],
                'page_start' => $old['pageStart'],
                'page_end' => $old['pageEnd'],
                'content_hash' => null,
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $ids
     * @return array<string,array{fragment_id:string,label:?string,source_id:?string,source_name:?string,page_start:?int,page_end:?int,content_hash:?string}>
     */
    private function unresolved(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['fragment_id' => $id, 'label' => null, 'source_id' => null, 'source_name' => null, 'page_start' => null, 'page_end' => null, 'content_hash' => null];
        }

        return $out;
    }
}
