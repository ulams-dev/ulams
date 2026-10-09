<?php

namespace Ulams\ExamplePlugin\Http\Controllers\Admin\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\ExamplePlugin\Http\Requests\Admin\SendGreetingRequest;

interface GreetingAdminApiSwagger
{
    /**
     * @OA\Post(
     *      path="/api/admin/example-plugin/greetings",
     *      summary="Send the tenant's greeting to a user (dispatches GreetingSent)",
     *      tags={"Admin Example plugin"},
     *      security={{"passport": {}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/ExamplePluginSendGreetingRequest")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="data", ref="#/components/schemas/ExamplePluginGreetingResource"),
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Not authenticated"),
     *      @OA\Response(response=403, description="Missing the example-plugin_send-greeting permission"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function send(SendGreetingRequest $request): JsonResponse;
}
