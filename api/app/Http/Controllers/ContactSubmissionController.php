<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Ulams\TemplatesEmail\Core\EmailMailable;

class ContactSubmissionController
{
    /**
     * @OA\Post(
     *   path="/api/contact",
     *   operationId="submitPublicContactMessage",
     *   tags={"Contact"},
     *   summary="Send a message to the ULAMS team",
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"name","email","subject","message"},
     *     @OA\Property(property="name", type="string", maxLength=120),
     *     @OA\Property(property="email", type="string", format="email", maxLength=254),
     *     @OA\Property(property="subject", type="string", maxLength=160),
     *     @OA\Property(property="message", type="string", maxLength=6000),
     *     @OA\Property(property="website", type="string", description="Spam honeypot; leave empty")
     *   )),
     *   @OA\Response(response=202, description="Message delivered to the private contact inbox"),
     *   @OA\Response(response=422, description="Input validation failed"),
     *   @OA\Response(response=429, description="Rate limit exceeded"),
     *   @OA\Response(response=503, description="Message delivery is temporarily unavailable")
     * )
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:6000'],
            'website' => ['nullable', 'string', 'max:200'],
        ]);

        // Honeypot submissions get a normal success response without reaching the inbox.
        if (trim((string) ($data['website'] ?? '')) !== '') {
            return response()->json(['message' => 'Message received.'], 202);
        }

        $content = '<h2>New ULAMS contact message</h2>'
            . '<p><strong>Name:</strong> ' . e($data['name']) . '</p>'
            . '<p><strong>Email:</strong> ' . e($data['email']) . '</p>'
            . '<p><strong>Subject:</strong> ' . e($data['subject']) . '</p>'
            . '<p><strong>Message:</strong><br>' . nl2br(e($data['message'])) . '</p>';

        $mail = new EmailMailable();
        $mail->to((string) config('services.contact.recipient'));
        $mail->replyTo($data['email'], $data['name']);
        $mail->subject('ULAMS contact form message');
        $mail->html($content);

        try {
            Mail::send($mail);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Message delivery is temporarily unavailable. Please try again later.'], 503);
        }

        return response()->json(['message' => 'Message received.'], 202);
    }
}
