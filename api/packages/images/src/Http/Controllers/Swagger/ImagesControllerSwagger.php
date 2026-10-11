<?php

namespace Ulams\Images\Http\Controllers\Swagger;

use Ulams\Images\Http\Requests\ImagesRenderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SWAGGER_VERSION
 */
interface ImagesControllerSwagger
{
    /**
     * @OA\Get(
     *     path="/api/images/img",
     *     summary="Resize an image on the fly and redirect to the cached copy",
     *     description="Public endpoint, throttled by `images.render`. The cached file name is `sha1(path . json_encode(params))` plus the extension, so the same request always returns the same URL. The first request creates the copy, optimises it and stores it on the configured disk; later requests only redirect. A failed resize does not return an error status: the redirect points to an SVG that contains the error message.",
     *     tags={"Images"},
     *     @OA\Parameter(
     *         name="path",
     *         in="query",
     *         required=true,
     *         description="Path of the source image on the storage disk",
     *         @OA\Schema(type="string", example="avatars/1/avatar.jpg")
     *     ),
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         required=false,
     *         description="Predefined size from `config('images.public.size_definitions')`; wins over `w` and `h`",
     *         @OA\Schema(type="string", example="thumbnail")
     *     ),
     *     @OA\Parameter(
     *         name="w",
     *         in="query",
     *         required=false,
     *         description="Target width in pixels; the aspect ratio is kept and the image is never enlarged",
     *         @OA\Schema(type="integer", example=100)
     *     ),
     *     @OA\Parameter(
     *         name="h",
     *         in="query",
     *         required=false,
     *         description="Target height in pixels; the aspect ratio is kept and the image is never enlarged",
     *         @OA\Schema(type="integer", example=100)
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         required=false,
     *         description="Output format; the source extension is kept when it is omitted or unsupported",
     *         @OA\Schema(type="string", enum={"jpg", "png", "gif", "webp"})
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirect to the cached, resized file",
     *         @OA\Header(header="Location", description="URL of the cached file", @OA\Schema(type="string"))
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests (`images.render` throttle)",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Too Many Attempts."))
     *     ),
     * )
     */
    public function image(Request $request): RedirectResponse;

    /**
     * @OA\Post(
     *     path="/api/images/img",
     *     summary="Resize many images at once",
     *     description="Public endpoint, throttled by `images.render`. Returns one entry per requested image, in the same order, with the URL of the cached copy.",
     *     tags={"Images"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"paths"},
     *             @OA\Property(
     *                 property="paths",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=20,
     *                 @OA\Items(
     *                     required={"path"},
     *                     @OA\Property(property="path", type="string", description="Path of the source image on the storage disk", example="avatars/1/avatar.jpg"),
     *                     @OA\Property(
     *                         property="params",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="size", type="string", nullable=true, description="Predefined size", example="thumbnail"),
     *                         @OA\Property(property="w", type="integer", nullable=true, minimum=1, maximum=4096, example=100),
     *                         @OA\Property(property="h", type="integer", nullable=true, minimum=1, maximum=4096, example=100),
     *                     ),
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resized images",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="url", type="string", example="http://localhost/storage/imgcache/68840270724ee4ff0b481f4fbd2299e13dfe2447.jpg"),
     *                 @OA\Property(property="path", type="string", example="imgcache/68840270724ee4ff0b481f4fbd2299e13dfe2447.jpg"),
     *                 @OA\Property(property="hash", type="string", example="68840270724ee4ff0b481f4fbd2299e13dfe2447"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error (more than 20 paths, a dimension outside 1-4096)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests (`images.render` throttle)",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Too Many Attempts."))
     *     ),
     * )
     */
    public function images(ImagesRenderRequest $request): JsonResponse;
}
