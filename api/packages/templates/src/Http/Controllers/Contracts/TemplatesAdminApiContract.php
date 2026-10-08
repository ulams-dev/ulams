<?php

namespace EscolaLms\Templates\Http\Controllers\Contracts;

use EscolaLms\Templates\Http\Requests\TemplateAssignedRequest;
use EscolaLms\Templates\Http\Requests\TemplateAssignRequest;
use EscolaLms\Templates\Http\Requests\TemplateCreateRequest;
use EscolaLms\Templates\Http\Requests\TemplateDeleteRequest;
use EscolaLms\Templates\Http\Requests\TemplateListAssignableRequest;
use EscolaLms\Templates\Http\Requests\TemplateListingRequest;
use EscolaLms\Templates\Http\Requests\TemplateReadRequest;
use EscolaLms\Templates\Http\Requests\TemplateUpdateRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

interface TemplatesAdminApiContract
{
    /**
     * @OA\Get(
     *     path="/api/admin/templates",
     *     summary="Lists available templates",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Channel class for filtering",
     *         in="query",
     *         name="channel",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Event class for filtering",
     *         in="query",
     *         name="event",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="list of available templates",
     *         @OA\MediaType(
     *            mediaType="application/json",
     *            @OA\Schema(
     *                type="object",
     *                description="map of templates identified by a slug value",
     *                @OA\AdditionalProperties(
     *                    ref="#/components/schemas/Template"
     *                )
     *            )
     *         )
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateListingRequest $request
     * @return JsonResponse
     */
    public function list(TemplateListingRequest $request): JsonResponse;

    /**
     * @OA\Post(
     *     path="/api/admin/templates",
     *     summary="Create a new template identified by id",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         description="Template attributes",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Template")
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="template created successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=409,
     *          description="there already is a template identified by chosen slug identifier",
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="one of the parameters has invalid format",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateCreateRequest $request
     * @return JsonResponse
     */
    public function create(TemplateCreateRequest $request): JsonResponse;

    /**
     * @OA\Patch(
     *     path="/api/admin/templates/{id}",
     *     summary="Update an existing template identified by id",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable template identifier",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         description="Template attributes",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Template")
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="template updated successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=400,
     *          description="cannot find a template with provided slug identifier",
     *      ),
     *     @OA\Response(
     *          response=422,
     *          description="one of the parameters has invalid format",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateUpdateRequest $request
     * @return JsonResponse
     */
    public function update(TemplateUpdateRequest $request, int $id): JsonResponse;

    /**
     * @OA\Delete(
     *     path="/api/admin/templates/{id}",
     *     summary="Delete a template identified by a id",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable template identifier",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="template deleted successfully",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *      ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=400,
     *          description="cannot find a template with provided slug identifier",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateDeleteRequest $request
     * @return JsonResponse
     */
    public function delete(TemplateDeleteRequest $request, int $id): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/templates/{id}",
     *     summary="Read a template identified by a given id identifier",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable template identifier",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="",
     *         @OA\JsonContent(ref="#/components/schemas/Template")
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateListingRequest $request
     * @return JsonResponse
     */
    public function read(TemplateReadRequest $request, int $id): JsonResponse;


    /**
     * @OA\Get(
     *     path="/api/admin/templates/variables",
     *     summary="Dictionary list of available template variables",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable template identifier",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateListingRequest $request
     * @return JsonResponse
     */
    public function variables(TemplateReadRequest $request): JsonResponse;


    /**
     * @OA\Get(
     *     path="/api/admin/templates/{id}/preview",
     *     summary="Read a template identified by a given id identifier",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique human-readable template identifier",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Preview details",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateListingRequest $request
     * @return Response
     */
    public function preview(TemplateReadRequest $request, int $id): Response;

    /**
     * @OA\Post(
     *     path="/api/admin/templates/{id}/assign",
     *     summary="Assign template to model (of class assignable to the Variable Set)",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique id of template",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="assignable_id",
     *                  type="integer",
     *              ),
     *          )
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="Preview details",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateAssignRequest $request
     * @return Response
     */
    public function assign(TemplateAssignRequest $request, $id): Response;

    /**
     * @OA\Post(
     *     path="/api/admin/templates/{id}/unassign",
     *     summary="Remove template assignment to model",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Unique id of template",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="assignable_id",
     *                  type="integer",
     *              ),
     *          )
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="Preview details",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateAssignRequest $request
     * @return Response
     */
    public function unassign(TemplateAssignRequest $request, $id): Response;

    /**
     * @OA\Get(
     *     path="/api/admin/templates/assignable",
     *     summary="Get Templates that can be assigned to model",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Class of assignable model for which we want to find assignable Templates",
     *         in="query",
     *         name="assignable_class",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Class of channel for which we want to find assignable Templates",
     *         in="query",
     *         name="channel",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Class of event for which we want to find assignable Templates",
     *         in="query",
     *         name="event",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of Templates that can be assigned",
     *     ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateAssignedRequest $request
     * @return Response
     */
    public function assignable(TemplateListAssignableRequest $request): Response;

    /**
     * @OA\Get(
     *     path="/api/admin/templates/assigned",
     *     summary="Get Template assigned to the model",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\Parameter(
     *         description="Class of assignable model for which we want to fetch assigned Template",
     *         in="query",
     *         name="assignable_class",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Id of assignable model for which we want to fetch assigned Template",
     *         in="query",
     *         name="assignable_id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Preview details",
     *      ),
     *     @OA\Response(
     *          response=401,
     *          description="endpoint requires authentication",
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="user doesn't have required access rights",
     *      ),
     *     @OA\Response(
     *          response=500,
     *          description="server-side error",
     *      ),
     * )
     *
     * @param TemplateAssignedRequest $request
     * @return Response
     */
    public function assigned(TemplateAssignedRequest $request): Response;
}
