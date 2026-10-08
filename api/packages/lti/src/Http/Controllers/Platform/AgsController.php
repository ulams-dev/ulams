<?php

namespace Ulams\Lti\Http\Controllers\Platform;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\Lti\Exceptions\LtiRequestException;
use Ulams\Lti\Http\Middleware\IsolateLtiBearer;
use Ulams\Lti\Models\LtiLineItem;
use Ulams\Lti\Platform\AgsService;
use Ulams\Lti\Support\Lti;

/**
 * AGS 2.0 endpoints of the platform side. Authenticated with an access token from
 * /api/lti/platform/token; every line item belongs to the token's tool and the course in the URL.
 *
 * @OA\Get(path="/api/lti/platform/ags/{course}/lineitems", summary="AGS: list line items", tags={"LTI"},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="line item container"), @OA\Response(response=401, description="invalid token"))
 * @OA\Post(path="/api/lti/platform/ags/{course}/lineitems", summary="AGS: create a line item", tags={"LTI"},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=201, description="line item"), @OA\Response(response=403, description="scope lineitem required"))
 * @OA\Get(path="/api/lti/platform/ags/{course}/lineitems/{lineItem}", summary="AGS: read a line item", tags={"LTI"},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="lineItem", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="line item"))
 * @OA\Put(path="/api/lti/platform/ags/{course}/lineitems/{lineItem}", summary="AGS: update a line item", tags={"LTI"},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="lineItem", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="line item"))
 * @OA\Delete(path="/api/lti/platform/ags/{course}/lineitems/{lineItem}", summary="AGS: delete a line item", tags={"LTI"},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="lineItem", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=204, description="deleted"))
 * @OA\Post(path="/api/lti/platform/ags/{course}/lineitems/{lineItem}/scores", summary="AGS: post a score", tags={"LTI"},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="lineItem", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=204, description="stored; completes the topic when Completed or FullyGraded"))
 * @OA\Get(path="/api/lti/platform/ags/{course}/lineitems/{lineItem}/results", summary="AGS: latest result per learner", tags={"LTI"},
 *     @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="lineItem", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="result container"))
 */
class AgsController extends Controller
{
    private const LINEITEM = 'application/vnd.ims.lis.v2.lineitem+json';
    private const CONTAINER = 'application/vnd.ims.lis.v2.lineitemcontainer+json';
    private const RESULTS = 'application/vnd.ims.lis.v2.resultcontainer+json';

    public function __construct(private readonly AgsService $ags)
    {
    }

    public function index(Request $request, int $course): JsonResponse
    {
        return $this->guard($request, $course, [Lti::SCOPE_LINEITEM, Lti::SCOPE_LINEITEM_READONLY], function ($tool, $model) use ($request) {
            $items = LtiLineItem::query()
                ->where('lti_tool_id', $tool->getKey())
                ->where('course_id', $model->getKey())
                ->when($request->query('resource_link_id'), fn ($q, $id) => $q->where('topic_id', (int) preg_replace('/^topic-/', '', (string) $id)))
                ->when($request->query('resource_id'), fn ($q, $id) => $q->where('resource_id', $id))
                ->when($request->query('tag'), fn ($q, $tag) => $q->where('tag', $tag))
                ->orderBy('id')
                ->get();

            return response()->json($items->map(fn ($item) => $this->ags->serializeLineItem($item))->all())
                ->header('Content-Type', self::CONTAINER);
        });
    }

    public function store(Request $request, int $course): JsonResponse
    {
        return $this->guard($request, $course, [Lti::SCOPE_LINEITEM], function ($tool, $model) use ($request) {
            $item = $this->ags->fillLineItem(new LtiLineItem(['lti_tool_id' => $tool->getKey(), 'course_id' => $model->getKey()]), $request->json()->all(), $model);

            return response()->json($this->ags->serializeLineItem($item), 201)->header('Content-Type', self::LINEITEM);
        });
    }

    public function show(Request $request, int $course, int $lineItem): JsonResponse
    {
        return $this->guard($request, $course, [Lti::SCOPE_LINEITEM, Lti::SCOPE_LINEITEM_READONLY], function ($tool, $model) use ($lineItem) {
            return response()->json($this->ags->serializeLineItem($this->ags->lineItem($tool, $model, $lineItem)))
                ->header('Content-Type', self::LINEITEM);
        });
    }

    public function update(Request $request, int $course, int $lineItem): JsonResponse
    {
        return $this->guard($request, $course, [Lti::SCOPE_LINEITEM], function ($tool, $model) use ($request, $lineItem) {
            $item = $this->ags->fillLineItem($this->ags->lineItem($tool, $model, $lineItem), $request->json()->all(), $model);

            return response()->json($this->ags->serializeLineItem($item))->header('Content-Type', self::LINEITEM);
        });
    }

    public function destroy(Request $request, int $course, int $lineItem): JsonResponse
    {
        return $this->guard($request, $course, [Lti::SCOPE_LINEITEM], function ($tool, $model) use ($lineItem) {
            $this->ags->lineItem($tool, $model, $lineItem)->delete();

            return response()->json(null, 204);
        });
    }

    public function scores(Request $request, int $course, int $lineItem): JsonResponse
    {
        return $this->guard($request, $course, [Lti::SCOPE_SCORE], function ($tool, $model) use ($request, $lineItem) {
            $this->ags->storeScore($tool, $this->ags->lineItem($tool, $model, $lineItem), $request->json()->all());

            return response()->json(null, 204);
        });
    }

    public function results(Request $request, int $course, int $lineItem): JsonResponse
    {
        return $this->guard($request, $course, [Lti::SCOPE_RESULT_READONLY], function ($tool, $model) use ($request, $lineItem) {
            return response()->json($this->ags->results($this->ags->lineItem($tool, $model, $lineItem), $request->query('user_id')))
                ->header('Content-Type', self::RESULTS);
        });
    }

    private function guard(Request $request, int $course, array $scopes, callable $callback): JsonResponse
    {
        try {
            [$tool, $granted] = $this->ags->authenticate(IsolateLtiBearer::token($request));
            AgsService::requireScope($granted, ...$scopes);

            return $callback($tool, $this->ags->course($tool, $course));
        } catch (LtiRequestException $e) {
            return response()->json(['error' => $e->oauthError, 'error_description' => $e->getMessage()], $e->status);
        }
    }
}
