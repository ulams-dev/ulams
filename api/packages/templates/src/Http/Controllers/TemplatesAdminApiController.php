<?php

namespace Ulams\Templates\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Templates\Dtos\TemplateFilterCriteriaDto;
use Ulams\Templates\Facades\Template as FacadesTemplate;
use Ulams\Templates\Http\Controllers\Contracts\TemplatesAdminApiContract;
use Ulams\Templates\Http\Requests\TemplateAssignedRequest;
use Ulams\Templates\Http\Requests\TemplateAssignRequest;
use Ulams\Templates\Http\Requests\TemplateCreateRequest;
use Ulams\Templates\Http\Requests\TemplateDeleteRequest;
use Ulams\Templates\Http\Requests\TemplateListAssignableRequest;
use Ulams\Templates\Http\Requests\TemplateListingRequest;
use Ulams\Templates\Http\Requests\TemplateReadRequest;
use Ulams\Templates\Http\Requests\TemplateUpdateRequest;
use Ulams\Templates\Http\Resources\TemplateResource;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Services\Contracts\TemplateServiceContract;
use Ulams\Templates\Services\Contracts\TemplateVariablesServiceContract;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TemplatesAdminApiController extends UlamsBaseController implements TemplatesAdminApiContract
{
    private TemplateServiceContract $templateService;

    public function __construct(TemplateServiceContract $templateService, TemplateVariablesServiceContract $variablesService)
    {
        $this->templateService = $templateService;
        $this->variablesService = $variablesService;
    }

    public function list(TemplateListingRequest $request): JsonResponse
    {
        $templates = $this->templateService->list(
            TemplateFilterCriteriaDto::instantiateFromRequest($request),
            OrderDto::instantiateFromRequest($request),
            $request->getPerPage(),
        );

        return $this->sendResponseForResource(TemplateResource::collection($templates), "templates list retrieved successfully");
    }

    public function create(TemplateCreateRequest $request): JsonResponse
    {
        $template = $this->templateService->insert($request->all());
        return $this->sendResponseForResource(TemplateResource::make($template), "template created successfully");
    }

    public function update(TemplateUpdateRequest $request, int $id): JsonResponse
    {
        $input = $request->all();

        $updated = $this->templateService->update($id, $input);
        if (!$updated) {
            return $this->sendError(sprintf("template id '%s' doesn't exists", $id), 404);
        }
        return $this->sendResponse($updated, "template updated successfully");
    }

    public function delete(TemplateDeleteRequest $request, int $id): JsonResponse
    {
        $deleted = $this->templateService->deleteById($id);
        if (!$deleted) {
            return $this->sendError(sprintf("template with id '%s' doesn't exists", $id), 404);
        }
        return $this->sendResponse($deleted, "template deleted successfully");
    }

    public function read(TemplateReadRequest $request, int $id): JsonResponse
    {
        $template = $this->templateService->getById($id);
        if ($template->exists) {
            return $this->sendResponseForResource(TemplateResource::make($template), "template fetched successfully");
        }
        return $this->sendError(sprintf("template with id '%s' doesn't exists", $id), 404);
    }

    public function events(TemplateReadRequest $request): JsonResponse
    {
        $vars = FacadesTemplate::getRegisteredEvents();

        return $this->sendResponse($vars, "events and handlers fetched successfully");
    }

    public function variables(TemplateReadRequest $request): JsonResponse
    {
        $vars = FacadesTemplate::getRegisteredEventsWithTokens();

        return $this->sendResponse($vars, "template vars fetched successfully");
    }

    public function preview(TemplateReadRequest $request, $id): Response
    {
        $template = Template::findOrFail($id);

        $preview = FacadesTemplate::sendPreview($request->user(), $template);

        return $this->sendResponse($preview->toArray(), "template preview fetched successfully");
    }

    public function assign(TemplateAssignRequest $request, $id): Response
    {
        $template = $request->getTemplate();

        if (!$template->is_assignable) {
            return $this->sendError(__('Template is not assignable.'));
        }

        $this->templateService->assignTemplateToModel($template, $request->input('assignable_id'));

        return $this->sendResponseForResource(TemplateResource::make($template), 'Template assigned');
    }

    public function unassign(TemplateAssignRequest $request, $id): Response
    {
        $template = $request->getTemplate();

        if (!$template->is_assignable) {
            return $this->sendError(__('Template is not assignable.'));
        }

        $this->templateService->unassignTemplateFromModel($template, $request->input('assignable_id'));

        return $this->sendResponseForResource(TemplateResource::make($template), 'Template unassigned');
    }

    public function assignable(TemplateListAssignableRequest $request): Response
    {
        $templates = FacadesTemplate::listAssignableTemplates($request->input('assignable_class'), $request->input('event'), $request->input('channel'));

        return $this->sendResponseForResource(TemplateResource::collection($templates), 'List of assignable templates fetched successfully');
    }

    public function assigned(TemplateAssignedRequest $request): Response
    {
        $templates = $this->templateService->findTemplatesAssignedToModel($request->input('assignable_class'), $request->input('assignable_id'));

        return $this->sendResponseForResource(TemplateResource::collection($templates), 'List of assigned templates fetched successfully');
    }
}
