<?php

namespace Ulams\Tutor\Services;

use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\Tutor\Models\Chunk;
use Ulams\Tutor\Retrieval\PostgresRetriever;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/**
 * Builds the searchable index of a course (ADR 0060). Builder courses: the lesson blocks of the applied
 * blueprint and the source text they cite. Other courses: rich text topics split at headings. Quiz
 * questions, options, explanations and final tests are never indexed, so the tutor cannot find them.
 */
class TutorIndexer
{
    /** @return int chunks written (0 when the index was already current) */
    public function ensureFresh(int $courseId): int
    {
        $signature = $this->signature($courseId);

        return $signature !== null && Chunk::query()->where('course_id', $courseId)->where('source_signature', $signature)->exists() ? 0 : $this->rebuild($courseId);
    }

    public function rebuild(int $courseId): int
    {
        $course = Course::query()->find($courseId);
        $signature = $this->signature($courseId);
        if ($course === null || $signature === null) {
            Chunk::query()->where('course_id', $courseId)->delete();

            return 0;
        }

        $session = Session::query()->where('course_id', $courseId)->whereNotNull('applied_version_id')->first();
        $version = $session ? Version::query()->find($session->applied_version_id) : null;
        [$rows, $language] = $version ? $this->fromBlueprint($session, $version->document) : [$this->fromTopics($course), (string) ($course->language ?? 'en')];

        DB::transaction(function () use ($courseId, $rows, $language, $signature) {
            Chunk::query()->where('course_id', $courseId)->delete();
            foreach (array_chunk($rows, 200) as $batch) {
                Chunk::query()->insert(array_map(fn (array $r) => [
                    'course_id' => $courseId, 'topic_id' => $r['topic_id'], 'element_id' => $r['element_id'] ?? null,
                    'fragment_ids' => json_encode($r['fragment_ids'] ?? []), 'label' => mb_substr($r['label'], 0, 255), 'text' => $r['text'],
                    'language' => $language, 'source_signature' => $signature,
                ], $batch));
            }
            if (DB::getDriverName() === 'pgsql') {
                DB::update("UPDATE tutor_chunks SET tsv = to_tsvector(?::regconfig, label || ' ' || text) WHERE course_id = ?", [PostgresRetriever::configFor($language), $courseId]);
            }
        });

        return count($rows);
    }

    /** What the index is built from: the applied version of a builder session, else a hash of the rich text topics. */
    public function signature(int $courseId): ?string
    {
        $applied = Session::query()->where('course_id', $courseId)->whereNotNull('applied_version_id')->value('applied_version_id');
        if ($applied) {
            return 'v:' . $applied;
        }
        $stamp = Topic::query()->where('topicable_type', RichText::class)->whereIn('lesson_id', DB::table('lessons')->where('course_id', $courseId)->select('id'))->selectRaw('COUNT(*) as n, MAX(updated_at) as u')->first();

        return $stamp && (int) $stamp->n > 0 ? 'h:' . md5($stamp->n . '|' . $stamp->u) : null;
    }

    /** @return array{0:array<int,array>,1:string} */
    private function fromBlueprint(Session $session, array $doc): array
    {
        $rows = [];
        $topicOf = fn (string $lessonId) => EntityMapEntry::query()->where('session_id', $session->id)->where('element_id', $lessonId)->where('entity_type', 'topic')->whereNull('retired_at')->value('entity_id');
        foreach (Blueprint::lessons($doc) as $item) {
            $lesson = $item['lesson'];
            $topicId = $topicOf($lesson['id']) ?: null;
            $title = 'Lesson ' . $item['number'] . ' ' . ($lesson['title'] ?? '');
            $seen = [];
            foreach ($lesson['blocks'] ?? [] as $b => $block) {
                foreach ($this->split((string) $block['markdown']) as $part) {
                    $rows[] = ['topic_id' => $topicId, 'element_id' => $block['id'], 'fragment_ids' => $block['citations'] ?? [], 'label' => trim($title) . ' › part ' . ($b + 1), 'text' => $part];
                }
                // the source text the block cites, once per lesson
                foreach ($block['citations'] ?? [] as $fid) {
                    if (isset($seen[$fid])) {
                        continue;
                    }
                    $seen[$fid] = true;
                    $fragment = Fragment::query()->find($fid);
                    foreach ($fragment ? $this->split($fragment->text) : [] as $part) {
                        $rows[] = ['topic_id' => $topicId, 'element_id' => $block['id'], 'fragment_ids' => [$fid], 'label' => trim($title) . ' › source: ' . $fragment->label(), 'text' => $part];
                    }
                }
            }
        }

        return [$rows, (string) ($doc['course']['language'] ?? 'en')];
    }

    /** @return array<int,array> */
    private function fromTopics(Course $course): array
    {
        $rows = [];
        $topics = Topic::query()->with('lesson', 'topicable')->where('topicable_type', RichText::class)->active()
            ->whereIn('lesson_id', DB::table('lessons')->where('course_id', $course->getKey())->select('id'))->orderBy('lesson_id')->orderBy('order')->get();
        foreach ($topics as $topic) {
            $html = (string) ($topic->topicable?->value ?? '');
            $sections = preg_split('/(?=<h[1-4][\s>])/i', $html, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($sections as $section) {
                $heading = preg_match('/<h[1-4][^>]*>(.*?)<\/h[1-4]>/is', $section, $m) ? trim(strip_tags($m[1])) : '';
                $text = trim(strip_tags((string) preg_replace('/<\/(p|li|div|h[1-6])>|<br\s*\/?>/i', "\n\n", $section)));
                foreach ($this->split($text) as $part) {
                    $rows[] = ['topic_id' => $topic->getKey(), 'label' => trim(($topic->lesson?->title ?? '') . ' › ' . $topic->title . ($heading !== '' ? ' › ' . $heading : '')), 'text' => $part];
                }
            }
        }

        return $rows;
    }

    /** @return string[] paragraphs grouped into chunks of at most `retrieval.chunk_chars` */
    private function split(string $text): array
    {
        $max = (int) config('tutor.retrieval.chunk_chars');
        $chunks = [];
        $current = '';
        foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            foreach (mb_strlen($paragraph) > $max ? mb_str_split($paragraph, $max) : [$paragraph] as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 2 > $max) {
                    $chunks[] = $current;
                    $current = '';
                }
                $current .= ($current === '' ? '' : "\n\n") . $piece;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
