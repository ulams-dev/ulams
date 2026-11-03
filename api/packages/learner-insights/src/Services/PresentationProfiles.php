<?php

namespace Ulams\LearnerInsights\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\UserSetting;
use Ulams\LearnerInsights\Signals\SignalType;

/**
 * The adaptive interface (ADR 0062): a presentation profile per learner and course, derived by rules from
 * the statuses and always overridable by the learner. It only chooses among options of the existing
 * learner components (density, steps or the whole lesson, navigation, visible hints); no model is
 * involved and nothing is generated. Off unless the tenant sets `learner_insights.adaptive_interface`.
 */
class PresentationProfiles
{
    public const DEFAULTS = ['density' => 'comfortable', 'chunking' => 'whole', 'navigation' => 'linear', 'hints' => 0];

    public const OPTIONS = ['density' => ['comfortable', 'compact'], 'chunking' => ['whole', 'steps'], 'navigation' => ['linear', 'overview'], 'hints' => [0, 1, 2, 3]];

    public function __construct(private readonly Eligibility $eligibility)
    {
    }

    /**
     * @return array{adaptive:bool,profile:array<string,mixed>,derived:array<string,mixed>,overrides:array<string,mixed>,why:array<int,string>}
     */
    public function forLearner(int $userId, int $courseId): array
    {
        $overrides = $this->overrides($userId, $courseId);
        if (!config('learner_insights.adaptive_interface') || !$this->eligibility->allowed($userId)) {
            return ['adaptive' => false, 'profile' => array_replace(self::DEFAULTS, $overrides), 'derived' => self::DEFAULTS, 'overrides' => $overrides, 'why' => []];
        }

        [$derived, $why] = $this->derive($userId, $courseId);

        return ['adaptive' => true, 'profile' => array_replace($derived, $overrides), 'derived' => $derived, 'overrides' => $overrides, 'why' => $why];
    }

    /** @param array<string,mixed> $changes only valid options are kept; null removes an override */
    public function override(int $userId, int $courseId, array $changes): array
    {
        $current = $this->overrides($userId, $courseId);
        foreach ($changes as $key => $value) {
            if (!isset(self::OPTIONS[$key])) {
                continue;
            }
            if ($value === null) {
                unset($current[$key]);
            } elseif (in_array($value, self::OPTIONS[$key], true)) {
                $current[$key] = $value;
            }
        }
        $key = "insights.presentation.$courseId";
        if ($current === []) {
            UserSetting::query()->where('user_id', $userId)->where('key', $key)->delete();
        } else {
            UserSetting::query()->updateOrCreate(['user_id' => $userId, 'key' => $key], ['value' => json_encode($current)]);
        }

        return $current;
    }

    /** @return array<string,mixed> */
    private function overrides(int $userId, int $courseId): array
    {
        $raw = UserSetting::query()->where('user_id', $userId)->where('key', "insights.presentation.$courseId")->value('value');
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? array_filter($data, fn ($v, $k) => isset(self::OPTIONS[$k]) && in_array($v, self::OPTIONS[$k], true), ARRAY_FILTER_USE_BOTH) : [];
    }

    /** @return array{0:array<string,mixed>,1:array<int,string>} */
    private function derive(int $userId, int $courseId): array
    {
        $profile = self::DEFAULTS;
        $why = [];
        $statuses = DB::table('learner_statuses')->where('user_id', $userId)->where('course_id', $courseId)->get(['element_key', 'status']);
        $struggling = $statuses->where('element_key', '!=', '')->where('status', 'struggling')->count();
        $course = $statuses->firstWhere('element_key', '') ?->status ?? 'on_track';

        if ($struggling >= 2) {
            $profile['chunking'] = 'steps';
            $profile['hints'] = 2;
            $why[] = 'Lessons are split into steps and hints are shown because a few parts of this course have been hard.';
        }
        if ($course === 'at_risk') {
            $profile['hints'] = max($profile['hints'], 3);
            $profile['navigation'] = 'linear';
            $why[] = 'The course outline stays linear so it is clear what comes next.';
        }
        if ($struggling === 0 && $course === 'on_track') {
            $done = DB::table('learner_signals')->where('user_id', $userId)->where('course_id', $courseId)->where('type', SignalType::TOPIC_COMPLETED)->where('occurred_at', '>=', CarbonImmutable::now()->subDays(14))->count();
            if ($done >= 5) {
                $profile['density'] = 'compact';
                $profile['navigation'] = 'overview';
                $why[] = 'The layout is more compact because you are moving through the course quickly.';
            }
        }

        return [$profile, $why];
    }
}
