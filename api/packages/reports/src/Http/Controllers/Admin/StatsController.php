<?php

namespace Ulams\Reports\Http\Controllers\Admin;

use Ulams\Cart\Models\Cart;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Reports\Http\Controllers\Admin\Swagger\StatsSwagger;
use Ulams\Reports\Http\Requests\Admin\CartStatsRequest;
use Ulams\Reports\Http\Requests\Admin\CourseStatsRequest;
use Ulams\Reports\Http\Requests\Admin\DateRangeStatsRequest;
use Ulams\Reports\Http\Requests\Admin\ExportCourseStatRequest;
use Ulams\Reports\Http\Requests\Admin\ExportTopicStatRequest;
use Ulams\Reports\Http\Requests\Admin\ImportCoursesStatsRequest;
use Ulams\Reports\Http\Requests\Admin\TopicStatsRequest;
use Ulams\Reports\Services\Contracts\StatsServiceContract;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StatsController extends UlamsBaseController implements StatsSwagger
{
    private StatsServiceContract $statsService;

    public function __construct(StatsServiceContract $statsService)
    {
        $this->statsService = $statsService;
    }

    public function available(): JsonResponse
    {
        return $this->sendResponse($this->statsService->getAvailableStats(), __('List of available stats for models'));
    }

    public function course(CourseStatsRequest $request): JsonResponse
    {
        $course = $request->getCourse();
        return $this->sendResponse($this->statsService->calculate($course, $request->getStats()), __('Stats for Course'));
    }

    public function topic(TopicStatsRequest $request): JsonResponse
    {
        $topic = $request->getTopic();
        return $this->sendResponse($this->statsService->calculate($topic, $request->getStats()), __('Stats for Topic'));
    }

    public function cart(CartStatsRequest $request): JsonResponse
    {
        return $this->sendResponse($this->statsService->calculate(new Cart(), $request->getStats()), __('Stats for Cart'));
    }

    public function dateRange(DateRangeStatsRequest $request): JsonResponse
    {
        return $this->sendResponse($this->statsService->calculate($request->getDateRange(), $request->getStats()), __('Stats for Models between dates'));
    }

    public function courseExport(ExportCourseStatRequest $request): BinaryFileResponse
    {
        return $this->statsService->export($request->getCourse(), $request->getStat());
    }

    public function courseImport(ImportCoursesStatsRequest $request)
    {
        $this->statsService->import($request->getCourse(), $request->file('file'));
    }

    public function topicExport(ExportTopicStatRequest $request): BinaryFileResponse
    {
        return $this->statsService->export($request->getTopic(), $request->getStat());
    }
}
