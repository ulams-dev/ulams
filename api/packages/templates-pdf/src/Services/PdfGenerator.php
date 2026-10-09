<?php

namespace Ulams\TemplatesPdf\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Ulams\Core\Models\User;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Facades\Template as TemplateFacade;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Ulams\TemplatesPdf\Exceptions\PdfRenderException;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Pdfme\PdfmeTemplate;
use Ulams\TemplatesPdf\Services\Contracts\PdfGeneratorContract;
use Ulams\TemplatesPdf\Services\Contracts\PdfRendererContract;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

class PdfGenerator implements PdfGeneratorContract
{
    public function __construct(private PdfRendererContract $renderer)
    {
    }

    public function pdf(FabricPDF $pdf): string
    {
        if ($pdf->path) {
            try {
                $disk = $this->disk();
                if ($disk->exists($pdf->path)) {
                    return (string) $disk->get($pdf->path);
                }
            } catch (Throwable) {
                // storage unavailable: render instead
            }
        }

        return $this->renderContent($pdf->content, $pdf->vars ?? []);
    }

    public function store(FabricPDF $pdf): string
    {
        $bytes = $this->renderContent($pdf->content, $pdf->vars ?? []);
        $directory = trim((string) $this->config('directory', 'pdfs'), '/');
        $path = $directory . '/' . $pdf->user_id . '/' . ($pdf->certificate_id ?: $pdf->getKey()) . '.pdf';

        if (!$this->disk()->put($path, $bytes)) {
            throw new PdfRenderException("Could not store the PDF at {$path}.");
        }
        $pdf->forceFill(['path' => $path])->saveQuietly();

        return $path;
    }

    public function renderContent(mixed $content, array $vars): string
    {
        $prepared = PdfmeTemplate::prepare(PdfmeTemplate::decode($content), $vars);

        return $this->renderer->render($prepared['template'], $prepared['inputs']);
    }

    public function preview(mixed $content, string $event, ?User $user = null): string
    {
        $variableClass = TemplateFacade::getVariableClassName($event, PdfChannel::class);
        if (!$variableClass) {
            throw new PdfRenderException("No PDF variables are registered for event {$event}.", 422, 'unknown_event');
        }
        $vars = array_merge(SettingsVariables::getSettingsValues(), $variableClass::mockedVariables($user));

        return $this->renderContent($content, $vars);
    }

    private function disk(): Filesystem
    {
        $disk = $this->config('disk');

        return $disk ? Storage::disk($disk) : Storage::disk();
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config(UlamsTemplatesPdfServiceProvider::CONFIG_KEY . '.storage.' . $key, $default);
    }
}
