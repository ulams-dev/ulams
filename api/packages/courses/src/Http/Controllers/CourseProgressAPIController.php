<?php

namespace Ulams\Courses\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Resources\Status;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Http\Controllers\Swagger\CourseProgressAPISwagger;
use Ulams\Courses\Http\Requests\CourseProgressAPIRequest;
use Ulams\Courses\Http\Requests\CourseProgressPaginatedListRequest;
use Ulams\Courses\Http\Resources\ProgressResource;
use Ulams\Courses\Repositories\Contracts\CourseRepositoryContract;
use Ulams\Courses\Repositories\Contracts\TopicRepositoryContract;
use Ulams\Courses\Services\Contracts\ProgressServiceContract;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseProgressAPIController extends AppBaseController implements CourseProgressAPISwagger
{
    protected ProgressServiceContract $progressServiceContract;
    protected TopicRepositoryContract $topicRepositoryContract;
    protected CourseRepositoryContract $courseRepositoryContract;

    public function __construct(
        ProgressServiceContract  $progressServiceContract,
        TopicRepositoryContract  $topicRepositoryContract,
        CourseRepositoryContract $courseRepositoryContract
    )
    {
        $this->progressServiceContract = $progressServiceContract;
        $this->topicRepositoryContract = $topicRepositoryContract;
        $this->courseRepositoryContract = $courseRepositoryContract;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->sendResponseForResource(
            ProgressResource::collection(
                $this->progressServiceContract->getByUser(
                    $request->user(),
                )),
            __('Progresses')
        );
    }

    public function indexPaginated(CourseProgressPaginatedListRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(
            ProgressResource::collection(
                $this->progressServiceContract->getByUserPaginated(
                    $request->user(),
                    OrderDto::instantiateFromRequest($request),
                    $request->get('per_page', 20),
                    $request->get('status', null),
                )),
            __('Progresses')
        );
    }

    /**
     * Display the specified CourseProgress.
     */
    public function show($course_id, Request $request): JsonResponse
    {
        $course = $this->courseRepositoryContract->getById($course_id);

        if ($course->status !== CourseStatusEnum::PUBLISHED) {
            // We only check $course->status !== CourseStatusEnum::PUBLISHED, and not is_active, because if course has deadline we still want to return progress
            return $this->sendError(__('Course is not active'), 403);
        }

        return $this->sendResponse(CourseProgressCollection::make($request->user(), $course)->getProgress(), __('Progress'));
    }

    /**
     * Update the specified CourseProgress in storage.
     */
    public function store($course_id, CourseProgressAPIRequest $request): JsonResponse
    {
        $course = $this->courseRepositoryContract->getById($course_id);

        if ($course->status !== CourseStatusEnum::PUBLISHED) {
            return $this->sendError(__('Course is not active'), 403);
        }

        $courseProgressCollection = $this->progressServiceContract->update($course, $request->user(), $request->get('progress'));

        if ($courseProgressCollection->afterDeadline()) {
            return $this->sendError(__('Deadline missed'), 403);
        }

        return $this->sendResponse($courseProgressCollection->getProgress(), __('Saved progress'));
    }

    public function ping($topic_id, Request $request): JsonResponse
    {
        $topic = $this->topicRepositoryContract->getById($topic_id);

        if ($topic->course->status !== CourseStatusEnum::PUBLISHED) {
            return $this->sendError(__('Course is not active'), 403);
        }
        if (!$topic->active) {
            return $this->sendError(__('Topic is not active'), 403);
        }

        $courseProgressCollection = $this->progressServiceContract->ping($request->user(), $topic);

        if ($courseProgressCollection->afterDeadline()) {
            return $this->sendError(__('Deadline missed'), 403);
        }

        return $this->sendResponseForResource(new Status(true), 'Status');
    }

    /**
     * Saves CourseH5PProgress in storage.
     */
    public function h5p($topic_id, Request $request): JsonResponse
    {
        $topic = $this->topicRepositoryContract->getById($topic_id);

        if (!$topic->course->is_active) {
            return $this->sendError(__('Course is not active'), 403);
        }
        if (!$topic->is_active) {
            return $this->sendError(__('Topic is not active'), 403);
        }

        $result = $this->progressServiceContract->h5p(
            $request->user(),
            $topic,
            $request->input('event'),
            $request->input('data'),
        );

        if ($result) {
            return $this->sendResponseForResource(new Status(true), 'Status');
        }
        return $this->sendError(__('Deadline missed'), 403);

    }
}
