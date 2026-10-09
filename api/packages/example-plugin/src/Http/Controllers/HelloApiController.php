<?php

namespace Ulams\ExamplePlugin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\ExamplePlugin\Http\Controllers\Swagger\HelloApiSwagger;
use Ulams\ExamplePlugin\Http\Resources\GreetingResource;
use Ulams\ExamplePlugin\Services\Contracts\GreetingServiceContract;

class HelloApiController extends UlamsBaseController implements HelloApiSwagger
{
    public function __construct(private GreetingServiceContract $greetings)
    {
    }

    public function hello(Request $request): JsonResponse
    {
        $name = $request->query('name');
        $name = is_string($name) && $name !== '' ? mb_substr($name, 0, 80) : null;

        return $this->sendResponseForResource(new GreetingResource($this->greetings->greeting($name)));
    }
}
