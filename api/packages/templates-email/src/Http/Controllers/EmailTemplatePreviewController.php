<?php

namespace Ulams\TemplatesEmail\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Qferrer\Mjml\Exception\ApiException;
use Qferrer\Mjml\Exception\CurlException;
use Qferrer\Mjml\Exception\ProcessException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Http\Requests\EmailImageUploadRequest;
use Ulams\TemplatesEmail\Http\Requests\EmailTemplateDraftRequest;
use Ulams\TemplatesEmail\Services\EmailDraftRenderer;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;

class EmailTemplatePreviewController extends UlamsBaseController
{
    public function __construct(private EmailDraftRenderer $renderer)
    {
    }

    /**
     * @OA\Post(
     *     path="/api/admin/templates/email/render", operationId="templates.email.render",
     *     summary="Render an unsaved e-mail template draft with sample data",
     *     description="Nothing is stored and nothing is sent. Returns the rendered HTML with the sample values of the event, the MJML errors reported by the renderer and accessibility warnings (warnings never fail the request). Throttled to 30 requests per minute per user.",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event", "title", "content"},
     *             @OA\Property(property="event", type="string", description="event full classname, as listed by GET /api/admin/templates/events"),
     *             @OA\Property(property="title", type="string", description="subject"),
     *             @OA\Property(property="content", type="string", description="MJML (or HTML) source, up to 512 KiB"),
     *             @OA\Property(property="sample_overrides", type="object", description="variable name to sample value, for this preview only", additionalProperties=@OA\AdditionalProperties(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="rendered draft",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmailTemplateDraft")
     *         )
     *     ),
     *     @OA\Response(response=401, description="endpoint requires authentication"),
     *     @OA\Response(response=403, description="user doesn't have required access rights"),
     *     @OA\Response(response=422, description="validation error"),
     *     @OA\Response(response=429, description="too many requests"),
     *     @OA\Response(response=503, description="the MJML renderer is unavailable"),
     * )
     *
     * @OA\Schema(
     *     schema="EmailTemplateDraft",
     *     @OA\Property(property="title", type="string", description="subject with the sample values"),
     *     @OA\Property(property="html", type="string", description="rendered e-mail HTML with the sample values"),
     *     @OA\Property(property="errors", type="array", description="MJML errors reported by the renderer", @OA\Items(type="string")),
     *     @OA\Property(property="missing_variables", type="array", description="variables required by the event that the draft does not use", @OA\Items(type="string")),
     *     @OA\Property(
     *         property="warnings",
     *         type="array",
     *         description="accessibility warnings of the generated e-mail",
     *         @OA\Items(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     ),
     *     @OA\Property(property="sent", type="boolean", description="only in the test-send response"),
     *     @OA\Property(property="recipient", type="string", description="only in the test-send response: the address of the signed-in admin")
     * )
     */
    public function render(EmailTemplateDraftRequest $request): JsonResponse
    {
        $draft = $this->draft($request);
        if ($draft === null) {
            return $this->sendError(__('The MJML renderer is unavailable.'), 503);
        }

        return $this->sendResponse($draft, __('Draft rendered.'));
    }

    /**
     * @OA\Post(
     *     path="/api/admin/templates/email/test-send", operationId="templates.email.test-send",
     *     summary="Send an unsaved e-mail template draft to the signed-in admin",
     *     description="Renders the draft like the render endpoint and sends it, with the subject prefixed by [Test], only to the e-mail address of the authenticated user. There is no recipient field. Throttled to 5 requests per minute per user.",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event", "title", "content"},
     *             @OA\Property(property="event", type="string"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="sample_overrides", type="object", additionalProperties=@OA\AdditionalProperties(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="the draft was sent to the signed-in admin",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmailTemplateDraft")
     *         )
     *     ),
     *     @OA\Response(response=401, description="endpoint requires authentication"),
     *     @OA\Response(response=403, description="user doesn't have required access rights"),
     *     @OA\Response(response=422, description="validation error"),
     *     @OA\Response(response=429, description="too many requests"),
     *     @OA\Response(response=503, description="the MJML renderer is unavailable"),
     * )
     */
    public function testSend(EmailTemplateDraftRequest $request): JsonResponse
    {
        $draft = $this->draft($request);
        if ($draft === null) {
            return $this->sendError(__('The MJML renderer is unavailable.'), 503);
        }

        // The only recipient is the signed-in admin: the request has no recipient field and none is read.
        $recipient = $request->user()->email;
        $mailable = new EmailMailable();
        $mailable->to($recipient);
        $mailable->subject('[Test] ' . $draft['title']);
        $mailable->html($draft['html']);
        Mail::send($mailable);

        return $this->sendResponse(array_merge($draft, ['sent' => true, 'recipient' => $recipient]), __('Test e-mail sent.'));
    }

    /**
     * @OA\Post(
     *     path="/api/admin/templates/email/images", operationId="templates.email.images",
     *     summary="Upload an image for an e-mail template",
     *     description="Stores a PNG, JPEG, GIF or WebP image (up to 2 MB by default) in the storage of the current tenant and returns its public URL, which e-mail clients fetch remotely. The type is sniffed from the content; SVG and every other type is refused. The stored name is random. Throttled to 30 requests per minute per user.",
     *     tags={"Templates"},
     *     security={
     *         {"passport": {}},
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image"},
     *                 @OA\Property(property="image", type="string", format="binary", description="PNG, JPEG, GIF or WebP file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="the stored image",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 @OA\Property(property="url", type="string", description="public URL to put in mj-image src"),
     *                 @OA\Property(property="name", type="string", description="path in the tenant storage"),
     *                 @OA\Property(property="mime", type="string"),
     *                 @OA\Property(property="size", type="integer", description="bytes"),
     *                 @OA\Property(property="width", type="integer"),
     *                 @OA\Property(property="height", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="endpoint requires authentication"),
     *     @OA\Response(response=403, description="user doesn't have required access rights"),
     *     @OA\Response(response=422, description="not an accepted image, or too large"),
     *     @OA\Response(response=429, description="too many requests"),
     * )
     */
    public function uploadImage(EmailImageUploadRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $disk = Storage::disk();
        $path = trim((string) config(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.images.directory', 'email-images'), '/')
            . '/' . Str::uuid() . '.' . $request->extension();
        // The extension comes from the sniffed type and the name is random: nothing of the client's name is kept.
        if ($disk->putFileAs(dirname($path), $file, basename($path), 'public') === false) {
            return $this->sendError(__('The image could not be stored.'), 500);
        }
        [$width, $height] = getimagesize($file->getRealPath());

        return $this->sendResponse([
            'url' => $disk->url($path),
            'name' => $path,
            'mime' => array_search($request->extension(), EmailImageUploadRequest::TYPES, true),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ], __('Image uploaded.'));
    }

    private function draft(EmailTemplateDraftRequest $request): ?array
    {
        try {
            return $this->renderer->render(
                $request->user(),
                $request->input('event'),
                $request->input('title'),
                $request->input('content'),
                $request->sampleOverrides()
            );
        } catch (ApiException | CurlException | ProcessException $e) {
            // Timeouts, connection errors and non-200 answers of the MJML service (see CurlApi), or a failing binary.
            Log::warning('Rendering an e-mail draft failed.', ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return null;
        }
    }
}
