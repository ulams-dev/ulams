<?php

namespace Ulams\Webinar\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Webinar\Enum\ConstantEnum;
use Ulams\Webinar\Http\Controllers\Swagger\WebinarAPISwagger;
use Ulams\Webinar\Http\Requests\ListWebinarsRequest;
use Ulams\Webinar\Http\Resources\WebinarSimpleResource;
use Ulams\Webinar\Services\Contracts\WebinarServiceContract;
use Illuminate\Http\JsonResponse;

class WebinarAPIController extends UlamsBaseController implements WebinarAPISwagger
{
    private WebinarServiceContract $webinarServiceContract;

    public function __construct(
        WebinarServiceContract $webinarServiceContract
    ) {
        $this->webinarServiceContract = $webinarServiceContract;
    }

    public function index(ListWebinarsRequest $listWebinarsRequest): JsonResponse
    {
        $search = $listWebinarsRequest->except(['limit', 'skip', 'order', 'order_by']);
        $orderDto = OrderDto::instantiateFromRequest($listWebinarsRequest);

        $webinars = $this->webinarServiceContract
            ->getWebinarsList(
                $search,
                !$listWebinarsRequest->input('only_incoming'),
                $orderDto,
                $listWebinarsRequest->input('only_incoming', false)
            )
            ->paginate(
                $listWebinarsRequest->get('per_page') ??
                config('ulams_webinar.perPage', ConstantEnum::PER_PAGE)
            );
        return $this->sendResponseForResource(
            $this->webinarServiceContract->extendResponse(WebinarSimpleResource::collection($webinars), true),
            __('Webinars retrieved successfully'),
        );
    }

    public function show(int $id): JsonResponse
    {
        $webinar = $this->webinarServiceContract->show($id);
        return $this->sendResponseForResource(
            $this->webinarServiceContract->extendResponse(WebinarSimpleResource::make($webinar), true),
            __('Webinar show successfully')
        );
    }

    public function forCurrentUser(ListWebinarsRequest $listWebinarsRequest): JsonResponse
    {
        $search = $listWebinarsRequest->except(['limit', 'skip', 'order', 'order_by']);
        $webinars = $this->webinarServiceContract->getWebinarsListForCurrentUser($search);
        if ($listWebinarsRequest->input('paginate', false)) {
            $webinars = $webinars->paginate(
                $listWebinarsRequest->get('per_page') ??
                config('ulams_webinar.perPage', ConstantEnum::PER_PAGE)
            );
        } else {
            $webinars = $webinars->get();
        }

        return $this->sendResponseForResource(
            $this->webinarServiceContract->extendResponse(WebinarSimpleResource::collection($webinars), true),
             __('Webinars retrieved successfully')
        );
    }

    public function generateJitsi(int $id): JsonResponse
    {
        return $this->sendResponse(
            $this->webinarServiceContract->generateJitsi($id),
            __('Webinar jitsi url generated successfully')
        );
    }

    public function startLiveStream(int $id): void
    {
        /**
         * @param string second param "testing" | "live" | "complete"
         */
        $this->webinarServiceContract->setStatusInLiveStreamInYt($id, 'live');
    }

    public function stopLiveStream(int $id): void
    {
        /**
         * @param string second param "testing" | "live" | "complete"
         */
        $this->webinarServiceContract->setStatusInLiveStreamInYt($id, 'complete');
    }
}
