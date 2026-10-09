<?php

namespace Ulams\TemplatesPdf\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\TemplatesPdf\Exceptions\PdfRenderException;
use Ulams\TemplatesPdf\Http\Controllers\Swagger\FabricPdfControllerSwagger;
use Ulams\TemplatesPdf\Http\Requests\PdfListingAdminRequest;
use Ulams\TemplatesPdf\Http\Requests\PdfListingRequest;
use Ulams\TemplatesPdf\Http\Requests\PdfPreviewRequest;
use Ulams\TemplatesPdf\Http\Requests\PdfReadRequest;
use Ulams\TemplatesPdf\Http\Resources\PdfListResource;
use Ulams\TemplatesPdf\Http\Resources\PdfResource;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Services\Contracts\PdfGeneratorContract;
use Ulams\TemplatesPdf\Services\Contracts\PdfRendererContract;

class FabricPdfController extends UlamsBaseController implements FabricPdfControllerSwagger
{
    public function __construct(
        private PdfGeneratorContract $generator,
        private PdfRendererContract $renderer
    ) {
    }

    public function index(PdfListingRequest $request): JsonResponse
    {
        $pdfs = FabricPDF::query()
            ->where('user_id', auth()->user()->id)
            ->when($request->has('assignable_type') && $request->has('assignable_id'),
                fn(Builder $query) => $query
                    ->where('assignable_type', $request->get('assignable_type'))
                    ->where('assignable_id', $request->get('assignable_id'))
            )
            ->paginate($request->get('per_page') ?? 15);

        return $this->sendResponseForResource(PdfListResource::collection($pdfs), "pdfs list retrieved successfully");
    }

    public function show(PdfReadRequest $request, int $id): JsonResponse
    {
        $pdf = FabricPDF::findOrFail($id);
        return $this->sendResponseForResource(PdfResource::make($pdf), "pdf fetched successfully");
    }

    public function generate(PdfReadRequest $request, int $id): Response
    {
        $pdf = FabricPDF::findOrFail($id);

        try {
            $bytes = $this->generator->pdf($pdf);
        } catch (PdfRenderException $e) {
            return $this->renderError($e);
        }

        return $this->pdfResponse($bytes, 'attachment', $this->fileName($pdf));
    }

    public function preview(PdfPreviewRequest $request): Response
    {
        try {
            $bytes = $this->generator->preview($request->getTemplateContent(), $request->input('event'), $request->user());
        } catch (PdfRenderException $e) {
            return $this->renderError($e);
        }

        return $this->pdfResponse($bytes, 'inline', 'preview.pdf');
    }

    public function admin(PdfListingAdminRequest $request): JsonResponse
    {
        if ($request->has('user_id')) {
            $pdfs = FabricPDF::where('user_id', $request->input('user_id'))->get();
        } else if ($request->has('template_id')) {
            $pdfs = FabricPDF::where('template_id', $request->input('template_id'))->get();
        } else {
            $pdfs = FabricPDF::paginate($request->get('per_page') ?? 15);
        }
        return $this->sendResponseForResource(PdfListResource::collection($pdfs), "pdfs list retrieved successfully");
    }

    public function fonts(): JsonResponse
    {
        try {
            $fonts = Cache::remember('templates_pdf.fonts', now()->addHour(), fn () => $this->renderer->fonts());
        } catch (PdfRenderException $e) {
            return $this->sendError($e->getMessage(), 503);
        }

        return $this->sendResponse($fonts, 'fonts retrieved successfully');
    }

    /**
     * Fonts are public (SIL OFL) and immutable: proxied from the renderer once
     * and kept on the local disk.
     */
    public function font(string $file): Response
    {
        if (!preg_match('/^[A-Za-z0-9_-]+\.ttf$/', $file)) {
            return $this->sendError('Unknown font', 404);
        }

        $path = storage_path('app/pdf-fonts/' . $file);
        if (!File::exists($path)) {
            try {
                $bytes = $this->renderer->font($file);
            } catch (PdfRenderException $e) {
                return $this->sendError($e->getStatus() === 404 ? 'Unknown font' : $e->getMessage(), $e->getStatus() === 404 ? 404 : 503);
            }
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $bytes);
        }

        return response()->file($path, [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'public, max-age=604800, immutable',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function pdfResponse(string $bytes, string $disposition, string $fileName): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $fileName . '"',
            'Content-Length' => (string) strlen($bytes),
        ]);
    }

    private function fileName(FabricPDF $pdf): string
    {
        $slug = \Illuminate\Support\Str::slug((string) ($pdf->title ?: 'certificate'));

        return ($slug ?: 'certificate') . '-' . $pdf->getKey() . '.pdf';
    }

    private function renderError(PdfRenderException $e): JsonResponse
    {
        $status = $e->getStatus();
        // renderer refused the template (4xx) => 422; renderer down or failing => 503
        $code = match (true) {
            $status === 422 || ($status !== null && $status >= 400 && $status < 500) => 422,
            default => 503,
        };

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'error' => $e->getErrorCode(),
            'details' => $e->getDetails(),
        ], $code);
    }
}
