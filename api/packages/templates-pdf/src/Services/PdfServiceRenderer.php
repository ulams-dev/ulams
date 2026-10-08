<?php

namespace Ulams\TemplatesPdf\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ulams\TemplatesPdf\Exceptions\PdfRenderException;
use Ulams\TemplatesPdf\Services\Contracts\PdfRendererContract;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;

/**
 * HTTP client of the PDF renderer service (api/pdf).
 */
class PdfServiceRenderer implements PdfRendererContract
{
    public function render(array $template, array $inputs): string
    {
        $response = $this->send(fn () => $this->request()
            ->accept('application/pdf')
            ->post($this->url('render'), ['template' => $template, 'inputs' => array_values($inputs)]));

        $pdf = $response->body();
        if (!str_starts_with($pdf, '%PDF-')) {
            throw new PdfRenderException('PDF service returned something that is not a PDF.', $response->status());
        }

        return $pdf;
    }

    public function fonts(): array
    {
        return $this->send(fn () => $this->request()->acceptJson()->get($this->url('fonts')))->json('data') ?? [];
    }

    public function font(string $file): string
    {
        return $this->send(fn () => $this->request()->get($this->url('fonts/' . rawurlencode($file))))->body();
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout((int) $this->config('timeout', 30));
        $token = $this->config('internal_token');
        if ($token) {
            $request = $request->withHeaders(['X-Internal-Token' => $token]);
        }

        return $request;
    }

    private function send(callable $call): Response
    {
        try {
            /** @var Response $response */
            $response = $call();
        } catch (ConnectionException $e) {
            throw new PdfRenderException('PDF service is unreachable: ' . $e->getMessage(), 503, 'service_unreachable', null, $e);
        }

        if ($response->failed()) {
            $message = $response->json('message') ?: Str::limit($response->body(), 200);
            throw new PdfRenderException(
                "PDF service error ({$response->status()}): {$message}",
                $response->status(),
                $response->json('error'),
                $response->json('details')
            );
        }

        return $response;
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->config('service_url'), '/') . '/' . ltrim($path, '/');
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config(UlamsTemplatesPdfServiceProvider::CONFIG_KEY . '.pdf.' . $key, $default);
    }
}
