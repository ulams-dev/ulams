<?php

namespace Ulams\Templates\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Templates\Http\Controllers\Contracts\EventAdminApiContract;
use Ulams\Templates\Http\Requests\EventTriggerRequest;
use Ulams\Templates\Services\Contracts\EventServiceContract;
use Illuminate\Http\JsonResponse;

class EventAdminApiController extends UlamsBaseController implements EventAdminApiContract
{
    private EventServiceContract $eventService;

    public function __construct(EventServiceContract $eventService)
    {
        $this->eventService = $eventService;
    }

    public function triggerManually(EventTriggerRequest $request): JsonResponse
    {
        $template = $request->getTemplate();

        if (!$template->is_valid) {
            return $this->sendError(__('Template is invalid.'), 400);
        }

        $this->eventService
            ->dispatchEventManuallyForUsers(
                $request->get('users'),
                $template,
                $request->input('course'),
                $request->input('product'),
            );

        return $this->sendSuccess(__('Event triggered successfully'));
    }
}
