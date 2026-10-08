<?php

namespace Ulams\Lrs\Http\Controllers\Xapi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Ulams\Lrs\Http\Middleware\AuthenticateXapiAccess;
use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Services\Contracts\XapiStatementServiceContract;
use Ulams\Lrs\Xapi\StatementValidator;
use Ulams\Lrs\Xapi\XapiException;

/**
 * xAPI Statement resource: POST, PUT and GET `.../xapi/std/statements`.
 */
class XapiStatementController extends Controller
{
    public function __construct(private readonly XapiStatementServiceContract $statements)
    {
    }

    public function post(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);
        $batch = StatementValidator::isObject($body) ? [$body] : $body;

        if (!is_array($batch) || !array_is_list($batch)) {
            throw XapiException::badRequest('The body must be a statement or a list of statements.');
        }

        return new JsonResponse($this->statements->store($batch, $this->access($request)));
    }

    public function put(Request $request): Response
    {
        $id = $request->query('statementId');

        if (!StatementValidator::isUuid($id)) {
            throw XapiException::badRequest('The [statementId] parameter must be a UUID.');
        }

        $statement = $this->jsonBody($request);

        if (!StatementValidator::isObject($statement)) {
            throw XapiException::badRequest('The body must be a single statement.');
        }
        if (isset($statement['id']) && is_string($statement['id']) && strcasecmp($statement['id'], $id) !== 0) {
            throw XapiException::badRequest('The statement id does not match the [statementId] parameter.');
        }

        $statement['id'] = $id;
        $this->statements->store([$statement], $this->access($request));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function get(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $statementId = $request->query('statementId');
        $voidedId = $request->query('voidedStatementId');

        if ($statementId !== null && $voidedId !== null) {
            throw XapiException::badRequest('Use either statementId or voidedStatementId.');
        }

        if ($statementId !== null || $voidedId !== null) {
            $single = $this->statements->find((string) ($statementId ?? $voidedId), $access, $voidedId !== null);

            return $this->json($single);
        }

        $result = $this->statements->query($request->query(), $access, $request->url());

        return $this->json($result);
    }

    private function json(array $data): JsonResponse
    {
        return (new JsonResponse($data, 200, [
            'X-Experience-API-Consistent-Through' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ]))->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function jsonBody(Request $request): mixed
    {
        if (str_starts_with(strtolower((string) $request->header('Content-Type')), 'multipart/')) {
            throw XapiException::badRequest('Statement attachments (multipart requests) are not supported by this store.');
        }

        $body = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw XapiException::badRequest('The body is not valid JSON.');
        }

        return $body;
    }

    private function access(Request $request): Access
    {
        return $request->attributes->get(AuthenticateXapiAccess::ACCESS_ATTRIBUTE);
    }
}
