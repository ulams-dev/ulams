<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Auth\Models\GroupUser;
use EscolaLms\Core\Models\User;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseGroupPivot;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\CourseUserPivot;
use EscolaLms\Courses\Models\Group;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Stats\Course\Strategies\TopicTitleStrategyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

class FinishedTopics extends AbstractCourseStat
{
    protected string $topicTable;
    protected string $lessonTable;
    protected string $courseTable;
    protected string $courseUserTable;
    protected string $userTable;
    protected string $courseProgressTable;
    protected string $groupTable;
    protected string $courseGroupTable;
    protected string $userGroupTable;

    public function __construct(Course $course)
    {
        parent::__construct($course);

        $this->topicTable = (new Topic())->getTable();
        $this->lessonTable = (new Lesson())->getTable();
        $this->courseTable = (new Course())->getTable();
        $this->courseUserTable = (new CourseUserPivot())->getTable();
        $this->userTable = (new User())->getTable();
        $this->courseProgressTable = (new CourseProgress())->getTable();
        $this->groupTable = (new Group())->getTable();
        $this->courseGroupTable = (new CourseGroupPivot())->getTable();
        $this->userGroupTable = (new GroupUser())->getTable();
    }

    public function calculate(): array
    {
        $individualUsers = $this->getIndividualUsers();
        $groupUsers = $this->getGroupUsers();

        $result = $individualUsers->concat($groupUsers);

        return $this->formatResult($result);
    }

    private function getBaseQuery(): Builder
    {
        return Topic::dontCache()
            ->select(
                $this->topicTable . '.id as topic_id',
                $this->topicTable . '.title as topic_title',
                $this->topicTable . '.topicable_id',
                $this->topicTable . '.topicable_type',
                $this->userTable . '.id as user_id',
                $this->userTable . '.email as user_email',
                $this->userTable . '.first_name as user_first_name',
                $this->userTable . '.last_name as user_last_name',
                $this->courseProgressTable . '.finished_at',
                $this->courseProgressTable . '.seconds',
                $this->courseProgressTable . '.started_at',
                $this->courseProgressTable . '.attempt',
            )
            ->with('topicable')
            ->join($this->lessonTable, $this->topicTable . '.lesson_id', '=', $this->lessonTable . '.id')
            ->join($this->courseTable, $this->lessonTable . '.course_id', '=', $this->courseTable . '.id')
            ->where($this->courseTable . '.id', '=', $this->course->getKey());
    }

    private function getIndividualUsers(): Collection
    {
        return $this->getBaseQuery()
            ->join($this->courseUserTable, $this->courseTable . '.id', '=', $this->courseUserTable . '.course_id')
            ->join($this->userTable, $this->courseUserTable . '.user_id', '=', $this->userTable . '.id')
            ->leftJoin($this->courseProgressTable, fn(JoinClause $join) => $join
                ->on($this->courseProgressTable . '.user_id', '=', $this->userTable . '.id')
                ->on($this->courseProgressTable . '.topic_id', '=', $this->topicTable . '.id')
            )
            ->get();
    }

    private function getGroupUsers(): Collection
    {
        return $this->getBaseQuery()
            ->join($this->courseGroupTable, $this->courseTable . '.id', '=', $this->courseGroupTable . '.course_id')
            ->join($this->groupTable, $this->courseGroupTable . '.group_id', '=', $this->groupTable . '.id')
            ->join($this->userGroupTable, $this->groupTable . '.id', '=', $this->userGroupTable . '.group_id')
            ->join($this->userTable, $this->userGroupTable . '.user_id', '=', $this->userTable . '.id')
            ->leftJoin($this->courseProgressTable, fn(JoinClause $join) => $join
                ->on($this->courseProgressTable . '.user_id', '=', $this->userTable . '.id')
                ->on($this->courseProgressTable . '.topic_id', '=', $this->topicTable . '.id')
            )
            ->get();
    }

    private function formatResult(Collection $result): array
    {
        return $result
            ->groupBy('user_email')
            ->map(fn($topics, $userEmail) => [
                'id' => $topics[0]->user_id,
                'name' => $topics[0]->user_first_name . ' ' . $topics[0]->user_last_name,
                'email' => $userEmail,
                'topics' => collect($topics)->map(fn($topic) => [
                    'id' => $topic->topic_id,
                    'title' => (new TopicTitleStrategyContext($topic))->getStrategy()->makeTitle(),
                    'started_at' => $topic->started_at,
                    'seconds' => $topic->seconds,
                    'finished_at' => $topic->finished_at,
                    'attempt' => $topic->attempt,
                    'topicable_type' => $topic->topicable_type,
                ]),
                'seconds_total' => collect($topics)->sum('seconds'),
            ])
            ->values()
            ->toArray();
    }
}
