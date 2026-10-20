<?php

namespace Ulams\KnowledgeExport\Services;

use InvalidArgumentException;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypes\Models\TopicContent\RichText;

/**
 * Builds the knowledge pack of one course: its learner-visible text as chunks with citations.
 *
 * What is exported: the course description and the text of every active rich-text topic of every
 * active lesson. Other topic types carry no text to chunk (video, H5P, quizzes, files) and are counted as
 * skipped. A chunk is cited only when its text is, verbatim, a block of the blueprint the course builder
 * applied: the block's source fragments become its citations. Text an author wrote or changed afterwards,
 * and courses written by hand, are exported with `uncited: true`. Nothing is guessed.
 *
 * Only courses that learners can see are exported (published, inside their active window).
 */
final class CoursePackBuilder
{
    public const FORMAT = 'ulams-knowledge-pack/1';

    public function __construct(private readonly Chunker $chunker, private readonly CitationResolver $citations)
    {
    }

    public static function exportable(Course $course): bool
    {
        return (bool) $course->is_active;
    }

    /**
     * @return array{manifest:array<string,mixed>,chunks:list<array<string,mixed>>,hash:string,uncited:int}
     */
    public function build(Course $course): array
    {
        if (!self::exportable($course)) {
            throw new InvalidArgumentException('Only published courses that are inside their active window can be exported.');
        }
        $blocks = $this->citations->blocksByTopic($course->getKey());
        $base = self::baseUrl();
        $courseUrl = $base . '/courses/' . $course->getKey();

        $chunks = [];
        $skipped = 0;
        $exported = 0;

        $overview = trim(implode("\n\n", array_filter([(string) $course->subtitle, (string) $course->description], fn ($t) => trim($t) !== '')));
        if ($overview !== '') {
            foreach ($this->chunker->chunks([['text' => $overview, 'citations' => []]]) as $i => $chunk) {
                $chunks[] = $this->row($course, null, null, 'course', $i, $chunk, $courseUrl, $course->updated_at?->toIso8601String());
            }
        }

        $lessons = $course->lessons()->active()->orderBy('order')->orderBy('id')->get();
        foreach ($lessons as $lesson) {
            $topics = Topic::query()->where('lesson_id', $lesson->getKey())->active()->orderBy('order')->orderBy('id')->get();
            foreach ($topics as $topic) {
                $content = $topic->topicable;
                if (!$content instanceof RichText || trim((string) $content->value) === '') {
                    $skipped++;
                    continue;
                }
                $exported++;
                $segments = $this->segments((string) $content->value, $blocks[$topic->getKey()] ?? []);
                $url = $base . '/learn/' . $course->getKey() . '/' . $topic->getKey();
                foreach ($this->chunker->chunks($segments) as $i => $chunk) {
                    $chunks[] = $this->row($course, $lesson, $topic, 'richtext', $i, $chunk, $url, $topic->updated_at?->toIso8601String());
                }
            }
        }

        // citations: one lookup for the whole course
        $ids = [];
        foreach ($chunks as $chunk) {
            foreach ($chunk['citations'] as $id) {
                $ids[$id] = true;
            }
        }
        $described = $this->citations->describe(array_keys($ids));
        $sources = [];
        foreach ($chunks as &$chunk) {
            $chunk['citations'] = array_map(fn (string $id) => $described[$id], $chunk['citations']);
            foreach ($chunk['citations'] as $citation) {
                if ($citation['source_id'] !== null) {
                    $sources[$citation['source_id']] = ['id' => $citation['source_id'], 'name' => $citation['source_name']];
                }
            }
        }
        unset($chunk);

        $uncited = count(array_filter($chunks, fn (array $c) => $c['uncited']));
        $hash = hash('sha256', json_encode(array_map(
            fn (array $c) => [$c['id'], $c['content_hash'], array_column($c['citations'], 'fragment_id')],
            $chunks,
        ), JSON_THROW_ON_ERROR));

        return [
            'manifest' => [
                'format' => self::FORMAT,
                'course' => ['id' => $course->getKey(), 'title' => (string) $course->title, 'language' => $course->language, 'url' => $courseUrl],
                'generated_at' => now()->toIso8601String(),
                'pack_hash' => $hash,
                'chunks' => count($chunks),
                'uncited_chunks' => $uncited,
                'topics' => ['exported' => $exported, 'skipped' => $skipped],
                'sources' => array_values($sources),
                'files' => ['chunks' => 'chunks.jsonl'],
            ],
            'chunks' => $chunks,
            'hash' => $hash,
            'uncited' => $uncited,
        ];
    }

    /**
     * The topic text as segments, cited where it is a block of the applied blueprint.
     *
     * @param list<array{text:string,citations:list<string>}> $blocks
     * @return list<array{text:string,citations:list<string>}>
     */
    private function segments(string $live, array $blocks): array
    {
        $segments = [];
        $cursor = 0;
        $gap = function (string $text) use (&$segments): void {
            // the "Sources" list the course builder appends is a rendering of the citations, not content;
            // text an author added after it stays
            $text = trim((string) preg_replace('/^\s*---\s+\*\*Sources\*\*\s+(?:\d+\. [^\n]*(?:\n|$))+/', '', trim($text)));
            if ($text !== '') {
                $segments[] = ['text' => $text, 'citations' => []];
            }
        };
        foreach ($blocks as $block) {
            if ($block['text'] === '' || ($pos = strpos($live, $block['text'], $cursor)) === false) {
                continue; // edited since: it is exported with the rest of the uncited text
            }
            $gap(substr($live, $cursor, $pos - $cursor));
            $segments[] = ['text' => $block['text'], 'citations' => $block['citations']];
            $cursor = $pos + strlen($block['text']);
        }
        $gap(substr($live, $cursor));

        return $segments;
    }

    /**
     * @param array{heading_path:list<string>,text:string,tokens:int,citations:list<string>} $chunk
     * @return array<string,mixed>
     */
    private function row(Course $course, ?object $lesson, ?Topic $topic, string $type, int $ordinal, array $chunk, string $url, ?string $updatedAt): array
    {
        $contentHash = hash('sha256', $chunk['text']);

        return [
            'id' => 'kch_' . substr(hash('sha256', $course->getKey() . ':' . ($topic?->getKey() ?? 0) . ':' . $ordinal), 0, 16),
            'course_id' => $course->getKey(),
            'course_title' => (string) $course->title,
            'lesson_id' => $lesson?->getKey(),
            'lesson_title' => $lesson?->title,
            'topic_id' => $topic?->getKey(),
            'topic_title' => $topic?->title,
            'topic_type' => $type,
            'ordinal' => $ordinal,
            'heading_path' => $chunk['heading_path'],
            'text' => $chunk['text'],
            'tokens' => $chunk['tokens'],
            'url' => $url,
            'citations' => $chunk['citations'],
            'uncited' => $chunk['citations'] === [],
            'content_hash' => $contentHash,
            'updated_at' => $updatedAt,
        ];
    }

    public static function baseUrl(): string
    {
        $configured = config('knowledge_export.base_url');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }
        $parts = parse_url((string) config('app.frontend_url'));
        if (is_array($parts) && isset($parts['host'])) {
            return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }

        return rtrim((string) config('app.url'), '/');
    }
}
