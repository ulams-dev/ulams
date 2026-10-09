<?php

namespace Ulams\LivingCourse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ulams\LivingCourse\Http\Controllers\Concerns\ResolvesLivingCourse;
use Ulams\LivingCourse\Services\StalenessService;

/**
 * Staleness per element and per course. Works with AI disabled.
 *
 * @OA\Get(path="/api/admin/living-course/sessions/{session}/staleness", summary="Per-element staleness and the course summary", tags={"Admin Living Course"}, security={{"passport": {}}},
 *     @OA\Parameter(name="session", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="summary and elements"), @OA\Response(response=403, description="another author's session"), @OA\Response(response=404, description="unknown session"))
 */
class StalenessController extends Controller
{
    use ResolvesLivingCourse;

    public function __construct(private readonly StalenessService $staleness)
    {
    }

    public function show(Request $request, string $session): JsonResponse
    {
        $s = $this->sessionFor($request, $session);

        return self::ok(['summary' => $this->staleness->summary($s), 'elements' => array_values($this->staleness->elements($s))]);
    }
}
