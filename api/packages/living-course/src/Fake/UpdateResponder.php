<?php

namespace Ulams\LivingCourse\Fake;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Fake\FakeResponders;
use Ulams\CourseBuilder\Fake\SyntheticResponders;

/**
 * A deterministic stand-in for the `update` task, used by the fake driver in `synthetic` mode when
 * no cassette matches (local demos without an API key, the end-to-end test). It reads the same
 * request the model would get and rewrites elements from the new source text, so every citation is
 * real and every changed number or fact shows up in the replacement. It is not a substitute for evals.
 */
final class UpdateResponder
{
    public static function register(FakeResponders $responders): void
    {
        $responders->register('update', fn (DriverRequest $r) => self::respond($r));
    }

    /** @return array<string,mixed> */
    public static function respond(DriverRequest $r): array
    {
        $fragments = SyntheticResponders::fragments($r);
        $diffs = self::diffs($r);
        $input = self::input($r);
        $items = [];
        foreach ($input['elements'] ?? [] as $el) {
            $type = (string) $el['type'];
            $changed = (array) ($el['changedFragments'] ?? []);
            $newIds = array_values(array_filter(array_map(fn ($c) => $c['newFragmentId'] ?? null, $changed), fn ($id) => $id !== null && isset($fragments[$id])));
            $base = [
                'elementId' => $el['elementId'],
                'decision' => 'no_change',
                'reason' => 'The source change does not affect what this says.',
                'severity' => 'minor',
                'fragmentIds' => $newIds,
                'answerStatus' => $type === 'question' ? 'unchanged' : 'not_a_question',
                'block' => null,
                'question' => null,
                'objective' => null,
            ];
            if ($newIds === [] && $changed !== []) {
                $items[] = $type === 'objective' ? $base : ['decision' => 'remove', 'reason' => 'The source section this relied on was removed.', 'severity' => 'major'] + $base;
                continue;
            }
            if ($newIds === [] || $type === 'objective') {
                $items[] = $base;
                continue;
            }
            $fragment = $fragments[$newIds[0]];
            $label = (string) $fragment['section'];
            $sentences = self::sentences($fragment['text']);
            $focus = self::focusSentence($sentences, $diffs[$newIds[0]] ?? []);
            if ($type === 'block') {
                $items[] = [
                    'decision' => 'update',
                    'reason' => mb_substr("{$label} changed in the source; the lesson text follows it now.", 0, 240),
                    'severity' => 'major',
                    'block' => [
                        'id' => (string) $el['element']['id'],
                        'kind' => (string) ($el['element']['kind'] ?? 'paragraph'),
                        'markdown' => mb_substr(implode(' ', array_slice($sentences, 0, 4)) ?: $fragment['text'], 0, 1500),
                        'citations' => [$newIds[0]],
                        'objectiveIds' => array_values((array) ($el['element']['objectiveIds'] ?? [])),
                    ],
                ] + $base;
                continue;
            }
            // question: the correct option becomes the sentence that carries the change
            $question = (array) $el['element'];
            $right = mb_substr($focus ?? ($sentences[0] ?? $fragment['text']), 0, 480);
            $options = [];
            $placed = false;
            foreach ($question['options'] as $o) {
                $isRight = (bool) $o['correct'] && !$placed;
                $placed = $placed || $isRight;
                $options[] = ['id' => (string) $o['id'], 'text' => $isRight ? $right : (string) $o['text'], 'correct' => (bool) $o['correct']];
            }
            $before = array_map(fn ($o) => $o['text'], array_filter((array) $question['options'], fn ($o) => $o['correct']));
            $changedAnswer = !in_array($right, $before, true);
            $items[] = [
                'decision' => 'update',
                'reason' => mb_substr("{$label} changed in the source; the correct answer now follows it.", 0, 240),
                'severity' => 'major',
                'answerStatus' => $changedAnswer ? 'changed' : 'unchanged',
                'question' => [
                    'id' => (string) $question['id'],
                    'type' => (string) $question['type'],
                    'stem' => (string) $question['stem'],
                    'options' => $options,
                    'explanation' => 'The source states: ' . $right,
                    'citations' => [$newIds[0]],
                    'objectiveIds' => array_values((array) ($question['objectiveIds'] ?? [])),
                ],
            ] + $base;
        }

        return ['items' => $items, 'reply' => sprintf('Reviewed %d element%s against the changed source.', count($items), count($items) === 1 ? '' : 's')];
    }

    /** @return array<string,mixed> */
    private static function input(DriverRequest $r): array
    {
        foreach ($r->blocks as $block) {
            if (preg_match('/<task_input>(.*?)<\/task_input>/s', $block->text, $m)) {
                return (array) json_decode($m[1], true);
            }
        }

        return [];
    }

    /** @return array<string,string[]> new fragment id => the words added by the source change */
    private static function diffs(DriverRequest $r): array
    {
        $out = [];
        foreach ($r->blocks as $block) {
            if (!preg_match_all('/<change id="\d+" kind="[a-z]+" magnitude="[a-z]+" old_fragment="[^"]*" new_fragment="(frg_[a-z2-7]{12})">(.*?)<\/change>/s', $block->text, $m, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $match) {
                if (preg_match_all('/\{\+(.*?)\+\}/s', $match[2], $added)) {
                    $out[$match[1]] = array_map(fn ($a) => html_entity_decode($a, ENT_QUOTES | ENT_XML1), $added[1]);
                }
            }
        }

        return $out;
    }

    /** @return string[] */
    private static function sentences(string $text): array
    {
        $text = (string) preg_replace('/```.*?```/s', '', $text);
        $text = strip_tags((string) preg_replace('/<script\b.*?<\/script>/is', '', $text));
        $text = trim((string) preg_replace('/\s+/', ' ', str_replace(['**', '__', '`', '#'], '', $text)));
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/u', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => str_word_count($s) >= 5 && mb_strlen($s) <= 400));
    }

    /** @param string[] $added */
    private static function focusSentence(array $sentences, array $added): ?string
    {
        foreach ($added as $chunk) {
            $needle = trim($chunk);
            foreach ($sentences as $s) {
                if ($needle !== '' && str_contains($s, $needle)) {
                    return $s;
                }
            }
        }

        return null;
    }
}
