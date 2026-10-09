<?php

namespace Ulams\CourseBuilder\Ingestion;

use RuntimeException;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * File to Markdown for the supported source kinds (markdown, pdf, docx). Used by the ingestor and
 * by Living Course revisions, so a re-upload is converted exactly like the first upload.
 */
final class SourceConverter
{
    /**
     * @param string $path a readable local file
     * @param string $kind markdown | pdf | docx
     * @throws RuntimeException when the file cannot be converted
     */
    public function toMarkdown(string $path, string $kind, ?string $filePath = null): ConvertedDocument
    {
        try {
            switch ($kind) {
                case 'pdf':
                    $pdf = (new PdfConverter())->convert($path, (int) config('course_builder.limits.pdf_pages', 300));

                    return new ConvertedDocument($pdf['markdown'], $pdf['page_marks'], ['kind' => $kind, 'pages' => $pdf['pages'], 'pdf_native' => $pdf['pdf_native'], 'title' => $pdf['title']], $filePath);
                case 'docx':
                    $docx = (new DocxConverter())->convert($path, (int) config('course_builder.limits.docx_uncompressed_bytes'));

                    return new ConvertedDocument($docx['markdown'], [], ['kind' => $kind, 'title' => $docx['title']], $filePath);
                default:
                    return new ConvertedDocument(SourceIngestor::cleanMarkdown((string) file_get_contents($path)), [], ['kind' => $kind], $filePath);
            }
        } catch (UploadRejected $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
