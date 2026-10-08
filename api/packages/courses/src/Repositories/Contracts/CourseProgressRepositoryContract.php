<?php

namespace Ulams\Courses\Repositories\Contracts;

use Carbon\Carbon;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Models\UserTopicTime;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Illuminate\Contracts\Auth\Authenticatable;

interface CourseProgressRepositoryContract extends BaseRepositoryContract
{
    public function findProgress(Topic $topic, Authenticatable $user): ?CourseProgress;

    public function getFieldsSearchable();

    public function model();

    public function updateInTopic(Topic $topic, Authenticatable $user, int $status, ?int $seconds = null, ?bool $newAttempt = false): void;

    public function getUserLastTimeInTopic(Authenticatable $user, Topic $topic, int $forgetAfter = CourseProgressCollection::FORGET_TRACKING_SESSION_AFTER_MINUTES): ?Carbon;

    public function updateUserTimeInTopic(Authenticatable $user, Topic $topic): void;

    public function getUserTimeInTopic(Authenticatable $user, Topic $topic, int $forgetAfter = CourseProgressCollection::FORGET_TRACKING_SESSION_AFTER_MINUTES): ?UserTopicTime;
}
