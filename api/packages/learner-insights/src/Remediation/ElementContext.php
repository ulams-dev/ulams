<?php

namespace Ulams\LearnerInsights\Remediation;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;

/**
 * What the remediation needs to know about one blueprint element, read from the applied version of
 * its session: the element, its lesson, the fragments it cites and the topic a learner sees it in.
 */
final class ElementContext
{
    /** @param array<string,mixed> $node @param string[] $fragmentIds */
    private function __construct(
        public readonly string $sessionId,
        public readonly string $elementId,
        public readonly string $appliedVersionId,
        public readonly string $type,
        public readonly string $label,
        public readonly array $node,
        public readonly array $lesson,
        public readonly array $fragmentIds,
        public readonly string $language,
        public readonly ?int $topicId,
    ) {
    }

    /** Elements that can be explained again: a text block, a whole lesson or a quiz question. */
    public const TYPES = ['block', 'lesson', 'question'];

    public static function forElement(string $elementId): ?self
    {
        $entry = EntityMapEntry::query()->where('element_id', $elementId)->whereNull('retired_at')->orderBy('id')->first();
        $session = $entry ? Session::query()->find($entry->session_id) : null;
        $version = $session?->applied_version_id ? Version::query()->find($session->applied_version_id) : null;
        $found = $version ? Blueprint::find($version->document, $elementId) : null;
        if ($found === null || !in_array($found['type'], self::TYPES, true)) {
            return null;
        }
        $doc = $version->document;
        $lesson = [];
        foreach (Blueprint::lessons($doc) as $item) {
            if ($item['lesson']['id'] === ($found['lessonId'] ?? null)) {
                $lesson = $item['lesson'];
            }
        }

        $ids = $found['node']['citations'] ?? [];
        if ($found['type'] === 'lesson') {
            foreach ($lesson['blocks'] ?? [] as $block) {
                $ids = array_merge($ids, $block['citations'] ?? []);
            }
        }
        // the lesson's own citations back an element that cites little
        $ids = array_values(array_unique(array_merge($ids, count($ids) < 2 ? ($lesson['citations'] ?? []) : [])));

        return new self(
            $session->id, $elementId, $version->id, $found['type'], $found['label'], $found['node'], $lesson, $ids,
            (string) ($doc['course']['language'] ?? 'en'),
            self::topicOf($session->id, $found, $lesson),
        );
    }

    /** The topic the learner works in: the lesson's topic, or the quiz topic of a question. */
    private static function topicOf(string $sessionId, array $found, array $lesson): ?int
    {
        $map = fn (string $element, string $type) => EntityMapEntry::query()->where('session_id', $sessionId)->where('element_id', $element)->where('entity_type', $type)->whereNull('retired_at')->value('entity_id');
        if ($found['type'] === 'question' && isset($lesson['quiz']['id']) && in_array($found['node']['id'], array_column($lesson['quiz']['questions'] ?? [], 'id'), true)) {
            return $map($lesson['quiz']['id'], 'quiz_topic') ?: null;
        }

        return isset($lesson['id']) ? ($map($lesson['id'], 'topic') ?: null) : null;
    }
}
