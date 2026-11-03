<?php

namespace Ulams\LearnerInsights\Services;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\ElementLookup;

/**
 * Which options of a quiz question a learner chose, as the option ids of the course content (ADR 0181,
 * plan question Q4): ids of the author's own text, not personal data, and only ever shown aggregated
 * above the minimum group size. Questions that were not built with the Course Builder, free-text answers
 * and answers that match no option record nothing.
 */
class ChosenOptions
{
    /** at most this many ids per answer, as long as a blueprint id */
    public const MAX = 6;

    /** @var array<string,array<int,array{id:string,text:string}>|null> */
    private array $options = [];

    public function __construct(private readonly ElementLookup $lookup)
    {
    }

    /**
     * @param array<string,mixed>|null $answer the stored GIFT answer (`text`, `multiple` or `bool`)
     * @return string[]
     */
    public function forAnswer(int $questionId, ?array $answer): array
    {
        if ($answer === null || $answer === []) {
            return [];
        }
        $options = $this->optionsOf($questionId);
        if ($options === null) {
            return [];
        }
        $given = [];
        if (isset($answer['multiple']) && is_array($answer['multiple'])) {
            $given = array_map('strval', array_filter($answer['multiple'], 'is_scalar'));
        } elseif (isset($answer['text']) && is_scalar($answer['text'])) {
            $given = [(string) $answer['text']];
        } elseif (array_key_exists('bool', $answer)) {
            $given = [filter_var($answer['bool'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'];
        }
        $ids = [];
        foreach ($given as $text) {
            $needle = self::plain($text);
            foreach ($options as $option) {
                if ($needle !== '' && $needle === self::plain($option['text'])) {
                    $ids[$option['id']] = true;
                }
            }
        }

        return array_slice(array_keys($ids), 0, self::MAX);
    }

    /** @return array<int,array{id:string,text:string}>|null */
    private function optionsOf(int $questionId): ?array
    {
        $key = (string) $questionId;
        if (!array_key_exists($key, $this->options)) {
            $mapped = $this->lookup->forGiftQuestion($questionId);
            $session = $mapped ? Session::query()->find($mapped['session_id']) : null;
            $version = $session?->applied_version_id ? Version::query()->find($session->applied_version_id) : null;
            $found = $version ? Blueprint::find($version->document, $mapped['element_id']) : null;
            $this->options[$key] = $found !== null && $found['type'] === 'question'
                ? array_values(array_map(fn ($o) => ['id' => (string) $o['id'], 'text' => (string) $o['text']], array_filter((array) ($found['node']['options'] ?? []), fn ($o) => ($o['id'] ?? '') !== '')))
                : null;
        }

        return $this->options[$key];
    }

    private static function plain(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', strip_tags($text))));
    }
}
