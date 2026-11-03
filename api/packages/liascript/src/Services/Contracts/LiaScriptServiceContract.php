<?php

namespace Ulams\LiaScript\Services\Contracts;

use Illuminate\Http\UploadedFile;
use Ulams\LiaScript\Models\LiaScriptDocument;
use Ulams\LiaScript\Models\LiaScriptVersion;

/**
 * Versioned LiaScript documents. Other packages (the Course Builder) create and update documents
 * through this contract instead of the concrete service.
 */
interface LiaScriptServiceContract
{
    /** Creates a document with its first version (Markdown text or an uploaded Markdown/zip file). */
    public function create(?string $title, ?string $markdown, ?UploadedFile $file, ?int $authorId): LiaScriptDocument;

    /** Adds a version when `$markdown` or `$file` is given; renames the document when `$title` is given. */
    public function update(LiaScriptDocument $document, ?string $title, ?string $markdown, ?UploadedFile $file, ?string $note, ?int $authorId): LiaScriptDocument;

    public function restore(LiaScriptDocument $document, int $version, ?int $authorId): LiaScriptDocument;

    public function source(LiaScriptDocument $document, ?int $version = null): LiaScriptVersion;

    public function delete(LiaScriptDocument $document): void;

    /** @return string[] warnings about the text (remote imports that will not load) */
    public function warnings(string $markdown): array;
}
