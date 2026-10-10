<?php

namespace Ulams\CourseBuilder\Quality;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Critique;
use Ulams\CourseBuilder\Models\Session;

/**
 * Critique results of the version being published, shaped for the publish summary (L2-18): what still
 * fails after the fixes (a warning, never a blocker: the author decides), what was skipped, and a count
 * per critic. Only the last iteration of a critic per lesson counts.
 */
final class CritiqueWarnings
{
    /**
     * Last verdict per lesson and critic of the session's current version.
     *
     * @return array<int,array{elementId:string,label:string,critic:string,verdict:string,iterations:int,issues:array}>
     */
    public static function latest(Session $session): array
    {
        $version = $session->currentVersion;
        if ($version === null) {
            return [];
        }
        // fixes make new versions with the lesson text of the old: the rows belong to the generated content version that began the chain
        $ids = [$version->id];
        for ($v = $version; $v !== null && count($ids) < 200; $v = $v->parent) {
            $ids[] = $v->id;
        }
        $rows = Critique::query()->where('session_id', $session->id)->whereIn('version_id', $ids)->orderBy('iteration')->get();
        $labels = [];
        foreach (Blueprint::lessons($version->document) as $item) {
            $labels[$item['lesson']['id']] = 'Lesson ' . $item['number'] . ' ' . $item['lesson']['title'];
        }
        $out = [];
        foreach ($rows as $row) {
            $key = $row->element_id . '|' . $row->critic;
            $out[$key] = ['elementId' => $row->element_id, 'label' => $labels[$row->element_id] ?? 'Lesson', 'critic' => $row->critic, 'verdict' => $row->verdict,
                'iterations' => $row->iteration, 'issues' => (array) $row->issues];
        }

        return array_values($out);
    }

    /** @return array<int,array<string,string>> publish-check warnings */
    public static function for(Session $session): array
    {
        $warnings = [];
        foreach (self::latest($session) as $c) {
            if ($c['verdict'] === 'fail' && $c['critic'] !== 'grounding') {
                $first = $c['issues'][0]['problem'] ?? 'See the lesson.';
                $warnings[] = ['code' => 'critique_failed', 'elementId' => $c['elementId'], 'message' => mb_substr("{$c['label']}: the {$c['critic']} review still finds a problem after {$c['iterations']} fix(es): {$first}", 0, 500)];
            } elseif ($c['verdict'] === 'skipped') {
                $warnings[] = ['code' => 'critique_skipped', 'elementId' => $c['elementId'], 'message' => mb_substr("{$c['label']}: the {$c['critic']} review was skipped. " . ($c['issues'][0]['problem'] ?? ''), 0, 500)];
            }
        }

        return array_slice($warnings, 0, 40);
    }

    /** @return array{critics:array<string,array{pass:int,fail:int,skipped:int}>,iterations:int} */
    public static function summary(Session $session): array
    {
        $critics = [];
        $iterations = 0;
        foreach (self::latest($session) as $c) {
            $critics[$c['critic']] ??= ['pass' => 0, 'fail' => 0, 'skipped' => 0];
            $critics[$c['critic']][$c['verdict']]++;
            $iterations = max($iterations, $c['iterations']);
        }

        return ['critics' => $critics, 'iterations' => $iterations];
    }
}
