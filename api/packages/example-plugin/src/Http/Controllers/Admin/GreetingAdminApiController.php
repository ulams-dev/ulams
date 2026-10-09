<?php

namespace Ulams\ExamplePlugin\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\ExamplePlugin\Http\Controllers\Admin\Swagger\GreetingAdminApiSwagger;
use Ulams\ExamplePlugin\Http\Requests\Admin\SendGreetingRequest;
use Ulams\ExamplePlugin\Http\Resources\GreetingResource;
use Ulams\ExamplePlugin\Services\Contracts\GreetingServiceContract;

class GreetingAdminApiController extends UlamsBaseController implements GreetingAdminApiSwagger
{
    public function __construct(private GreetingServiceContract $greetings)
    {
    }

    public function send(SendGreetingRequest $request): JsonResponse
    {
        $recipient = $request->getRecipient();
        $greeting = $this->greetings->send($recipient);

        return $this->sendResponseForResource(new GreetingResource($greeting, $recipient->getKey()), 'Greeting sent.');
    }
}
