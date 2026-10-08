<?php

namespace EscolaLms\Reports\Metrics;

use ArrayObject;
use EscolaLms\Core\Models\User;
use EscolaLms\Courses\Enum\CoursesPermissionsEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseAuthorPivot;
use EscolaLms\Reports\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TutorsPopularityMetric extends AbstractMetric
{
    public function calculate(?int $limit = null): Collection
    {
        $courseAuthorTable = (new CourseAuthorPivot())->getTable();
        $usersTable = (new User())->getTable();
        $courseUserPivot = (new Course())->users()->getTable();

        return DB::table($usersTable)
            ->selectRaw($usersTable . '.id, ' . $usersTable . '.email as label, COUNT(' . $courseUserPivot . '.user_id) as value')
            ->rightJoin($courseAuthorTable,  $courseAuthorTable . '.author_id', '=', $usersTable . '.id')
            ->join($courseUserPivot, $courseAuthorTable . '.course_id', '=', $courseUserPivot . '.course_id')
            ->groupBy($usersTable . '.id', $usersTable . '.email')
            ->orderBy('value', 'DESC')
            ->whereNotNull($usersTable . '.id')
            ->take($this->getLimit($limit))
            ->get(['id', 'label', 'value'])
            ->map(function ($item) {
                if (is_object($item)) {
                    $item = new ArrayObject($item);
                }
                if (is_array($item) || is_a($item, ArrayObject::class)) {
                    $item['value'] = is_null($item['value']) ? 0 : $item['value'];
                }
                return $item;
            })
            ->sortByDesc('value')
            ->values();
    }

    public function calculateAndStore(?int $limit = null): Report
    {
        /** @var Report $report */
        $report = Report::create([
            'metric' => get_class($this)
        ]);

        $results = $this->calculate($limit);

        foreach ($results as $result) {
            $report->measurements()->create([
                'label' => $result['label'] ?? 'unknown',
                'value' => $result['value'] ?? 0,
                'measurable_id' => $result['id'],
                'measurable_type' => User::class,
            ]);
        }

        return $report;
    }

    public function requiredPackage(): string
    {
        return 'escolalms/courses';
    }

    public static function requiredPackageInstalled(): bool
    {
        return class_exists(Course::class);
    }

    public static function requiredPermissions(): array
    {
        return [CoursesPermissionsEnum::COURSE_LIST];
    }
}
