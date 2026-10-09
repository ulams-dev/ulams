<?php

namespace Ulams\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ulams\Core\Http\Controllers\Swagger\CspReportControllerSwagger;
use Ulams\Core\Http\Requests\CspReportListRequest;
use Ulams\Core\Http\Resources\CspReportResource;
use Ulams\Core\Services\Contracts\CspReportServiceContract;

class CspReportController extends UlamsBaseController implements CspReportControllerSwagger
{
    public const MAX_BYTES = 16 * 1024;

    private const TYPES = ['application/csp-report', 'application/reports+json', 'application/json'];

    public function __construct(private readonly CspReportServiceContract $reports)
    {
    }

    public function store(Request $request): Response
    {
        $type = strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));
        if (!in_array($type, self::TYPES, true)) {
            return response('', 415);
        }

        $body = $request->getContent();
        if (strlen($body) > self::MAX_BYTES) {
            return response('', 413);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return response('', 400);
        }

        $this->reports->record($payload);

        return response('', 204);
    }

    public function index(CspReportListRequest $request): JsonResponse
    {
        $results = $this->reports->list($request->integer('per_page') ?: null);

        return $this->sendResponseForResource(CspReportResource::collection($results), __('CSP reports retrieved successfully'));
    }
}
