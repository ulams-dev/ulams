<?php

namespace Ulams\Lrs\Services\Contracts;

use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Models\XapiDocument;
use Ulams\Lrs\Xapi\DocumentScope;

interface XapiDocumentServiceContract
{
    /**
     * The document, or null when it does not exist.
     */
    public function find(DocumentScope $scope, Access $access): ?XapiDocument;

    /**
     * Ids of the documents in the scope, optionally only those updated after $since.
     *
     * @return string[]
     */
    public function ids(DocumentScope $scope, Access $access, ?string $since = null): array;

    /**
     * Create or replace (PUT), or create or merge JSON (POST) a document.
     *
     * @param string|null $ifMatch value of the If-Match header
     * @param string|null $ifNoneMatch value of the If-None-Match header
     */
    public function save(
        DocumentScope $scope,
        Access $access,
        string $content,
        string $contentType,
        bool $merge,
        ?string $ifMatch = null,
        ?string $ifNoneMatch = null,
    ): XapiDocument;

    /**
     * Delete the document, or every document in the scope when it has no document id.
     */
    public function delete(DocumentScope $scope, Access $access, ?string $ifMatch = null): void;

    /**
     * The ETag of a document (quoted SHA-1 of its content).
     */
    public function etag(XapiDocument $document): string;

    /**
     * The stored content as the response body.
     */
    public function body(XapiDocument $document): string;
}
