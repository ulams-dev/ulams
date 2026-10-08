<?php

namespace Ulams\Courses\Http\Controllers;

use Ulams\Courses\Http\Controllers\Swagger\LessonAPISwagger;
use Ulams\Courses\Http\Requests\CloneLessonAPIRequest;
use Ulams\Courses\Http\Requests\CreateLessonAPIRequest;
use Ulams\Courses\Http\Requests\DeleteLessonAPIRequest;
use Ulams\Courses\Http\Requests\GetLessonAPIRequest;
use Ulams\Courses\Http\Requests\UpdateLessonAPIRequest;
use Ulams\Courses\Http\Resources\Admin\LessonWithTopicsAdminResource;
use Ulams\Courses\Http\Resources\LessonResource;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Repositories\LessonRepository;
use Ulams\Courses\Services\Contracts\LessonServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class LessonController.
 */
class LessonAPIController extends AppBaseController implements LessonAPISwagger
{
    /** @var LessonRepository */
    private $lessonRepository;

    private LessonServiceContract $lessonService;

    public function __construct(LessonRepository $lessonRepo, LessonServiceContract $lessonService)
    {
        $this->lessonRepository = $lessonRepo;
        $this->lessonService = $lessonService;
    }

    public function index(Request $request)
    {
        $lessons = $this->lessonRepository->allMain(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponseForResource(LessonResource::collection($lessons), __('Lessons retrieved successfully'));
    }

    public function store(CreateLessonAPIRequest $request)
    {
        $input = $request->all();

        $lesson = $this->lessonRepository->create($input);

        return $this->sendResponseForResource(LessonResource::make($lesson), __('Lesson saved successfully'));
    }

    public function show($id, GetLessonAPIRequest $request)
    {
        $lesson = $request->getLesson();

        if (empty($lesson)) {
            return $this->sendError(__('Lesson not found'));
        }

        return $this->sendResponseForResource(LessonResource::make($lesson), __('Lesson retrieved successfully'));
    }

    public function update($id, UpdateLessonAPIRequest $request)
    {
        $input = $request->all();

        /** @var Lesson|null $lesson */
        $lesson = $this->lessonRepository->find($id);

        if (empty($lesson)) {
            return $this->sendError(__('Lesson not found'));
        }

        $lesson = $this->lessonRepository->update($input, $id);

        return $this->sendResponseForResource(LessonResource::make($lesson), __('Lesson updated successfully'));
    }

    public function destroy($id, DeleteLessonAPIRequest $request)
    {
        $lesson = $request->getLesson();

        if (empty($lesson)) {
            return $this->sendError(__('Lesson not found'));
        }

        $this->lessonRepository->delete($id);

        return $this->sendSuccess(__('Lesson deleted successfully'));
    }

    public function clone(CloneLessonAPIRequest $request): JsonResponse
    {
        $lesson = $request->getLesson();

        if (empty($lesson)) {
            return $this->sendError(__('Lesson not found'));
        }

        try {
            $lesson = $this->lessonService->cloneLesson($lesson);
        } catch (\Exception $error) {
            return $this->sendError(__('Error'), 400);
        }

        return $this->sendResponseForResource(LessonWithTopicsAdminResource::make($lesson), __('Lesson cloned successfully'));
    }
}
