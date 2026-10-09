<?php

namespace Ulams\ExamplePlugin\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface HelloApiSwagger
{
    /**
     * @OA\Get(
     *      path="/api/example-plugin/hello",
     *      summary="The tenant's greeting",
     *      tags={"Example plugin"},
     *      @OA\Parameter(name="name", in="query", required=false, @OA\Schema(type="string", maxLength=80)),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="data", ref="#/components/schemas/ExamplePluginGreetingResource"),
     *              @OA\Property(property="message", type="string")
     *          )
     *      )
     * )
     */
    public function hello(Request $request): JsonResponse;
}
