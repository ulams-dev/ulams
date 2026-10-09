<?php

namespace Ulams\Lrs\Http\Controllers\Xapi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Ulams\Lrs\Http\Middleware\AuthenticateXapiAccess;
use Ulams\Lrs\Services\Contracts\XapiDocumentServiceContract;
use Ulams\Lrs\Xapi\DocumentScope;
use Ulams\Lrs\Xapi\SessionScope;
use Ulams\Lrs\Xapi\XapiException;

/**
 * xAPI document resources: State (`activities/state`), Activity Profile
 * (`activities/profile`) and Agent Profile (`agents/profile`). The route passes the
 * resource name as the `resource` default.
 */
class XapiDocumentController extends Controller
{
    public function __construct(private readonly XapiDocumentServiceContract $documents)
    {
    }

    public function get(Request $request): Response|JsonResponse
    {
        $scope = $this->scope($request);
        $access = $request->attributes->get(AuthenticateXapiAccess::ACCESS_ATTRIBUTE);

        if ($scope->documentId === null) {
            return new JsonResponse($this->documents->ids($scope, $access, $request->query('since')));
        }

        $document = $this->documents->find($scope, $access);

        if (!$document) {
            throw XapiException::notFound();
        }

        return new Response($this->documents->body($document), Response::HTTP_OK, [
            'Content-Type' => $document->data->type ?? 'application/octet-stream',
            'ETag' => $this->documents->etag($document),
            'Last-Modified' => $document->updated_at?->toRfc7231String(),
        ]);
    }

    public function put(Request $request): Response
    {
        return $this->save($request, false);
    }

    public function post(Request $request): Response
    {
        return $this->save($request, true);
    }

    public function delete(Request $request): Response
    {
        $scope = $this->scope($request);

        if ($request->route('resource') !== 'state') {
            $scope->requireDocumentId();
        }

        $this->documents->delete(
            $scope,
            $request->attributes->get(AuthenticateXapiAccess::ACCESS_ATTRIBUTE),
            $request->header('If-Match'),
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function save(Request $request, bool $merge): Response
    {
        $scope = $this->scope($request)->requireDocumentId();

        $this->documents->save(
            $scope,
            $request->attributes->get(AuthenticateXapiAccess::ACCESS_ATTRIBUTE),
            $request->getContent(),
            (string) ($request->header('Content-Type') ?: 'application/octet-stream'),
            $merge,
            $request->header('If-Match'),
            $request->header('If-None-Match'),
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function scope(Request $request): DocumentScope
    {
        SessionScope::of($request)?->assertDocumentAccess(
            (string) $request->route('resource'),
            $request->getMethod(),
            $request->query('registration') ?? ($request->isJson() ? null : $request->request->get('registration')),
        );

        return match ($request->route('resource')) {
            'state' => DocumentScope::state($request),
            'activity_profile' => DocumentScope::activityProfile($request),
            'agent_profile' => DocumentScope::agentProfile($request),
        };
    }
}
