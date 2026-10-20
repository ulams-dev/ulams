<?php

namespace Ulams\TemplatesPdf\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TemplatesPdf\Http\Controllers\Swagger\CertificateVerificationControllerSwagger;
use Ulams\TemplatesPdf\Http\Requests\CertificateRevokeRequest;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Services\CertificateVerificationService;

class CertificateVerificationController extends UlamsBaseController implements CertificateVerificationControllerSwagger
{
    public function __construct(private CertificateVerificationService $service)
    {
    }

    public function show(string $certificateId): JsonResponse
    {
        $pdf = $this->service->find($certificateId);
        if (!$pdf) {
            return $this->sendError('Certificate not found', 404);
        }

        return $this->sendResponse($this->service->publicView($pdf), 'certificate verified');
    }

    public function revoke(CertificateRevokeRequest $request, int $id): JsonResponse
    {
        $pdf = $this->service->revoke(FabricPDF::findOrFail($id), $request->user(), (string) $request->input('reason'));

        return $this->sendResponse([
            'id' => $pdf->getKey(),
            'status' => $pdf->status(),
            'revoked_at' => $pdf->revoked_at?->toIso8601String(),
        ], 'certificate revoked');
    }
}
