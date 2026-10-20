<?php

namespace Ulams\LearnerInsights\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface PresentationSwagger
{
    /**
     * @OA\Get(
     *   path="/api/learner-insights/courses/{course}/presentation",
     *   summary="How the course is presented to the learner (density, steps, navigation, hints) and why",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="`adaptive` (the tenant switch), `profile` (what to show), `derived` (what the rules chose), `overrides` (the learner's choices), `why`"),
     *   @OA\Response(response=403, description="The learner may not attend the course")
     * )
     */
    public function show(Request $request, int $course): JsonResponse;

    /**
     * @OA\Put(
     *   path="/api/learner-insights/courses/{course}/presentation",
     *   summary="Override how the course is presented (for example show the whole lesson); null removes an override",
     *   tags={"Learner insights"},
     *   security={{"passport": {}}},
     *   @OA\Parameter(name="course", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(@OA\JsonContent(
     *     @OA\Property(property="density", type="string", enum={"comfortable", "compact"}, nullable=true),
     *     @OA\Property(property="chunking", type="string", enum={"whole", "steps"}, nullable=true),
     *     @OA\Property(property="navigation", type="string", enum={"linear", "overview"}, nullable=true),
     *     @OA\Property(property="hints", type="integer", enum={0, 1, 2, 3}, nullable=true)
     *   )),
     *   @OA\Response(response=200, description="The profile after the change"),
     *   @OA\Response(response=403, description="The learner may not attend the course"),
     *   @OA\Response(response=422, description="Invalid option")
     * )
     */
    public function update(Request $request, int $course): JsonResponse;
}
