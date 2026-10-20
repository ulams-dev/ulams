<?php

namespace Ulams\TemplatesPdf\Http\Controllers\Swagger;

use Illuminate\Http\JsonResponse;
use Ulams\TemplatesPdf\Http\Requests\CertificateRevokeRequest;

interface CertificateVerificationControllerSwagger
{
    /**
     * @OA\Get(
     *      path="/api/certificates/verify/{certificateId}",
     *      summary="Verify a certificate",
     *      tags={"Template PDFs"},
     *      description="Public and throttled. Returns the status (valid, expired or revoked), the course, the holder name (masked per tenant setting), issue and expiry dates. Unknown ids answer 404.",
     *      @OA\Parameter(
     *          name="certificateId",
     *          description="random certificate id encoded in the QR code",
     *          @OA\Schema(type="string"),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  @OA\Property(property="success", type="boolean"),
     *                  @OA\Property(property="message", type="string"),
     *                  @OA\Property(
     *                      property="data",
     *                      type="object",
     *                      @OA\Property(property="certificate_id", type="string"),
     *                      @OA\Property(property="status", type="string", enum={"valid", "expired", "revoked"}),
     *                      @OA\Property(property="title", type="string"),
     *                      @OA\Property(property="course_title", type="string", nullable=true),
     *                      @OA\Property(property="holder_name", type="string", nullable=true),
     *                      @OA\Property(property="issued_at", type="string", format="date-time"),
     *                      @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
     *                      @OA\Property(property="revoked_at", type="string", format="date-time", nullable=true),
     *                      @OA\Property(property="issuer", type="string"),
     *                  ),
     *              ),
     *          ),
     *      ),
     *      @OA\Response(response=404, description="unknown certificate id"),
     *      @OA\Response(response=429, description="too many requests"),
     * )
     */
    public function show(string $certificateId): JsonResponse;

    /**
     * @OA\Post(
     *      path="/api/admin/pdfs/{id}/revoke",
     *      summary="Revoke a certificate",
     *      tags={"Template PDFs"},
     *      description="Marks the certificate revoked. It stays verifiable and shows as revoked. Needs the fabricjs_pdf_revoke permission.",
     *      security={
     *         {"passport": {}},
     *      },
     *      @OA\Parameter(
     *          name="id",
     *          description="id of the generated PDF",
     *          @OA\Schema(type="integer"),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  type="object",
     *                  required={"reason"},
     *                  @OA\Property(property="reason", type="string", maxLength=500),
     *              ),
     *          ),
     *      ),
     *      @OA\Response(response=200, description="successful operation", @OA\MediaType(mediaType="application/json")),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=404, description="unknown PDF"),
     *      @OA\Response(response=422, description="Validation error"),
     * )
     */
    public function revoke(CertificateRevokeRequest $request, int $id): JsonResponse;
}
