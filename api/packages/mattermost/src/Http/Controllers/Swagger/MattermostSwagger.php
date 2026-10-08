<?php

namespace EscolaLms\Mattermost\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface MattermostSwagger
{
    /**
     * @OA\Get(
     *      path="/api/mattermost/me",
     *      summary="List all of the available mattermost teams and channels for a user",
     *      tags={"Mattermost"},
     *      description="ist all of the available mattermost teams and channels for a user",
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *      )
     * )
     */
    public function me(Request $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/mattermost/generate_credentials",
     *      summary="Generates user (creates one if not exists) credentials and sets a new random password",
     *      tags={"Mattermost"},
     *      description="Generates user (creates one if not exists) credentials and sets a new random password",
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *      )
     * )
     */
    public function generateCredentials(Request $request): JsonResponse;

    /**
     * @OA\Get(
     *      path="/api/mattermost/reset_password",
     *      summary="Sends request for mattermost to send reset password link with email",
     *      tags={"Mattermost"},
     *      description="Sends request for mattermost to send reset password link with email",
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *      )
     * )
     */
    public function resetPassword(Request $request): JsonResponse;
}
