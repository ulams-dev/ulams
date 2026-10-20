<?php

namespace Ulams\LearnerInsights\Fake;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Fake\FakeResponders;
use Ulams\CourseBuilder\Fake\SyntheticResponders;

/**
 * Deterministic stand-ins for the `remediation` and `reflection_feedback` tasks, used by the fake
 * driver in `synthetic` mode (local demos without an API key, tests). They read the same request
 * the model would get and answer from the fragments it contains, so every citation is real. They
 * are not a substitute for evals.
 */
final class RemediationResponders
{
    public static function register(FakeResponders $responders): void
    {
        $responders->register('remediation', fn (DriverRequest $r) => self::remediation($r));
        $responders->register('reflection_feedback', fn (DriverRequest $r) => self::reflection($r));
    }

    /** @return array<string,mixed> */
    public static function remediation(DriverRequest $r): array
    {
        $fragments = array_values(SyntheticResponders::fragments($r));
        $input = self::input($r);
        $kind = (string) ($input['kind'] ?? 'explanation');
        $first = $fragments[0] ?? ['id' => 'frg_aaaaaaaaaaaa', 'section' => 'Source', 'text' => ''];
        $ids = array_slice(array_column($fragments, 'id'), 0, 2) ?: [$first['id']];
        $sentence = trim(preg_split('/(?<=[.!?])\s+/', trim((string) $first['text']))[0] ?? '') ?: 'Review the section.';
        $block = ['kind' => $kind, 'title' => 'Another way to see it', 'markdown' => $sentence, 'citations' => $ids];
        if ($kind === 'worked_example') {
            $block['markdown'] = "Step 1. {$sentence}\n\nStep 2. Apply it to a small case of your own.";
        }
        if ($kind === 'practice') {
            $block['practice'] = [
                'stem' => 'Think about "' . $first['section'] . '" once more. Which of these would a careful reader agree with?',
                'options' => [['text' => $sentence, 'correct' => true], ['text' => 'None of the above applies.', 'correct' => false], ['text' => 'The section says the opposite.', 'correct' => false]],
                'explanation' => $sentence,
            ];
        }

        return ['summary' => 'A short ' . str_replace('_', ' ', $kind) . ' of "' . $first['section'] . '".', 'blocks' => [$block]];
    }

    /** @return array<string,mixed> */
    public static function reflection(DriverRequest $r): array
    {
        $fragments = array_values(SyntheticResponders::fragments($r));
        $first = $fragments[0] ?? ['id' => 'frg_aaaaaaaaaaaa', 'section' => 'Source', 'text' => ''];
        $sentence = trim(preg_split('/(?<=[.!?])\s+/', trim((string) $first['text']))[0] ?? '') ?: 'the section';

        return [
            'correct' => 'You touched the main idea of "' . $first['section'] . '".',
            'missing' => 'The section also says: ' . $sentence,
            'followUp' => 'How would you apply this to a new example?',
            'citations' => [$first['id']],
        ];
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
}
