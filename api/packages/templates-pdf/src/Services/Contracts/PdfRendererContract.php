<?php

namespace Ulams\TemplatesPdf\Services\Contracts;

use Ulams\TemplatesPdf\Exceptions\PdfRenderException;

/**
 * Renders pdfme templates to PDF (the api/pdf service).
 */
interface PdfRendererContract
{
    /**
     * @param array $template pdfme template (basePdf, schemas)
     * @param array<int, array<string, string>> $inputs one record per rendered copy, keyed by field name
     * @return string PDF bytes
     * @throws PdfRenderException
     */
    public function render(array $template, array $inputs): string;

    /**
     * Fonts the renderer knows: [['name' => 'NotoSans-Regular', 'file' => 'NotoSans-Regular.ttf', 'fallback' => true], ...]
     *
     * @throws PdfRenderException
     */
    public function fonts(): array;

    /**
     * TTF bytes of one bundled font file.
     *
     * @throws PdfRenderException
     */
    public function font(string $file): string;
}
