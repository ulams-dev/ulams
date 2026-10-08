<?php

namespace Ulams\TemplatesPdf\Services\Contracts;

use Ulams\Core\Models\User;
use Ulams\TemplatesPdf\Exceptions\PdfRenderException;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * Turns stored PDF records and templates into PDF bytes through the renderer.
 */
interface PdfGeneratorContract
{
    /**
     * PDF of an issued record: the stored file, or a fresh render of its template and variables.
     *
     * @throws PdfRenderException
     */
    public function pdf(FabricPDF $pdf): string;

    /**
     * Renders the record and stores the file on the configured disk; returns the path.
     *
     * @throws PdfRenderException
     */
    public function store(FabricPDF $pdf): string;

    /**
     * Renders template content (pdfme JSON string or array) with the given variables.
     *
     * @throws PdfRenderException
     */
    public function renderContent(mixed $content, array $vars): string;

    /**
     * Renders template content with the mocked variables of the event's PDF variable class.
     *
     * @throws PdfRenderException
     */
    public function preview(mixed $content, string $event, ?User $user = null): string;
}
