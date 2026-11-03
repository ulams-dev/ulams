<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\LearnerInsights\Tests\TestCase;

class CohortStatsTest extends TestCase
{
    public function testItStoresTheMedianTimeOfLearnersWhoFinishedTheElement(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        // three learners finish after 100, 200 and 900 seconds; a fourth is still reading
        foreach ([100, 200, 900] as $seconds) {
            $this->finish($topic, $this->learner($course), $seconds);
        }
        $this->progress($topic, $this->learner($course), ProgressStatus::IN_PROGRESS, 5000);

        $this->artisan('learner-insights:cohort-stats')->assertSuccessful();

        $row = DB::table('learner_insights_element_stats')->where('course_id', $course->getKey())->first();
        $this->assertSame('topic:' . $topic->getKey(), $row->element_key);
        $this->assertSame(3, (int) $row->learners);
        $this->assertSame(200.0, (float) $row->median_seconds);

        // idempotent: a second run updates the row
        $this->artisan('learner-insights:cohort-stats')->assertSuccessful();
        $this->assertSame(1, DB::table('learner_insights_element_stats')->count());
    }
}
