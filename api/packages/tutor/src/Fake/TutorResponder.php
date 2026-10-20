<?php

namespace Ulams\Tutor\Fake;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Fake\FakeResponders;

/**
 * Deterministic stand-in for the `tutor` task (fake driver, `synthetic` mode): answers with the best
 * matching chunk, declines when nothing matches or a quiz attempt is open. Not a substitute for evals.
 */
final class TutorResponder
{
    public static function register(FakeResponders $responders): void
    {
        $responders->register('tutor', fn (DriverRequest $r) => self::respond($r));
    }

    /** @return array<string,mixed> */
    public static function respond(DriverRequest $r): array
    {
        $text = implode("\n", array_map(fn ($b) => $b->text, $r->blocks));
        preg_match_all('/<chunk id="(c\d+)" label="[^"]*">\n(.*?)\n<\/chunk>/s', $text, $m, PREG_SET_ORDER);
        $chunks = array_map(fn ($c) => ['id' => $c[1], 'text' => html_entity_decode($c[2], ENT_QUOTES | ENT_XML1)], $m);
        preg_match_all('/<learner_message>(.*?)<\/learner_message>/s', $text, $q);
        $question = html_entity_decode((string) (end($q[1]) ?: ''), ENT_QUOTES | ENT_XML1);

        if (str_contains($text, '<mode>attempt_active</mode>')) {
            return ['answer' => "I can't help with quiz answers while a quiz attempt is open.", 'citations' => [], 'in_scope' => false, 'refusal' => 'attempt_active'];
        }
        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [], fn ($w) => mb_strlen($w) >= 4);
        $best = null;
        $bestScore = 0;
        foreach ($chunks as $c) {
            $score = count(array_filter($words, fn ($w) => str_contains(mb_strtolower($c['text']), $w)));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$c, $score];
            }
        }
        if ($best === null) {
            return ['answer' => 'That is outside this course.', 'citations' => [], 'in_scope' => false, 'refusal' => 'out_of_scope'];
        }
        $sentence = trim(preg_split('/(?<=[.!?])\s+/', trim($best['text']))[0] ?? $best['text']);

        return ['answer' => $sentence, 'citations' => [$best['id']], 'in_scope' => true, 'refusal' => 'none'];
    }
}
