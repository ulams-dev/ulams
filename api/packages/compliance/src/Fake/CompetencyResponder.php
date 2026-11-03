<?php

namespace Ulams\Compliance\Fake;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Fake\FakeResponders;

/**
 * A deterministic stand-in for the `competency_tags` task, used by the fake driver in `synthetic` mode when no
 * cassette matches (local demos without an API key): a competency is suggested for the lessons whose title or
 * summary contains its name. It reads the same request the model gets. Not a substitute for the model.
 */
final class CompetencyResponder
{
    public static function register(FakeResponders $responders): void
    {
        $responders->register('competency_tags', fn (DriverRequest $r) => self::respond($r));
    }

    /** @return array{suggestions: list<array{competencyId: int, rationale: string, lessonIds: list<int>}>} */
    public static function respond(DriverRequest $request): array
    {
        $text = implode("\n", array_map(fn ($block) => $block->text, $request->blocks));
        preg_match_all('/<competency id="(\d+)"[^>]*>(.*?)<\/competency>/s', $text, $competencies, PREG_SET_ORDER);
        preg_match_all('/<lesson id="(\d+)" title="([^"]*)">(.*?)<\/lesson>/s', $text, $lessons, PREG_SET_ORDER);

        $suggestions = [];
        foreach ($competencies as [, $id, $name]) {
            $name = html_entity_decode($name, ENT_QUOTES);
            $needle = mb_strtolower(trim($name));
            $hits = [];
            foreach ($lessons as [, $lessonId, $title, $summary]) {
                if ($needle !== '' && str_contains(mb_strtolower(html_entity_decode($title . ' ' . $summary, ENT_QUOTES)), $needle)) {
                    $hits[] = (int) $lessonId;
                }
            }
            if ($hits !== []) {
                $suggestions[] = ['competencyId' => (int) $id, 'rationale' => "The lessons teach {$name}.", 'lessonIds' => array_slice($hits, 0, 10)];
            }
        }

        return ['suggestions' => array_slice($suggestions, 0, 12)];
    }
}
