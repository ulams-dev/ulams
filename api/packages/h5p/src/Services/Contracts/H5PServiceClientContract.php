<?php

namespace Ulams\H5P\Services\Contracts;

use Illuminate\Http\UploadedFile;
use Ulams\H5P\Exceptions\H5PServiceException;

/**
 * Server-to-server client of the H5P service (api/h5p). Calls are made with
 * X-Internal-Token, so the service runs them as its system user.
 */
interface H5PServiceClientContract
{
    /**
     * Imports a .h5p package (installs its libraries if needed).
     *
     * @param UploadedFile|string $file uploaded file or local path of a .h5p package
     * @return int id of the new content (h5p.contents.id)
     * @throws H5PServiceException
     */
    public function upload($file): int;

    /**
     * Creates a content from its parameters (content.json) without a package. The library must be
     * installed; the service validates the save against it.
     *
     * @param string $library uber name, e.g. "H5P.Blanks 1.14"
     * @param array<string,mixed> $params content.json
     * @param array<string,mixed> $metadata at least `title`; `language` and `license` default to "en" and "U"
     * @return int id of the new content (h5p.contents.id)
     * @throws H5PServiceException
     */
    public function create(string $library, array $params, array $metadata): int;

    /**
     * Replaces the parameters and metadata of a content (keeps its id).
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $metadata
     * @throws H5PServiceException (status 404 for unknown content)
     */
    public function update(int $id, string $library, array $params, array $metadata): void;

    /**
     * Libraries installed on the platform: uber name ("H5P.Blanks 1.14") keyed by machine name; when
     * several versions are installed the newest one.
     *
     * @return array<string,string>
     * @throws H5PServiceException
     */
    public function libraries(): array;

    /**
     * Exports a content as a .h5p package.
     *
     * @return string local path of a temporary .h5p file; the caller deletes it
     * @throws H5PServiceException
     */
    public function download(int $id): string;

    /**
     * Deletes a content with its files, user states and results.
     *
     * @return bool false if the content did not exist
     * @throws H5PServiceException
     */
    public function delete(int $id): bool;

    /**
     * Row summary + h5p.json metadata:
     * {id, title, mainLibrary, libraryVersion, userId, createdAt, updatedAt, metadata}.
     *
     * @throws H5PServiceException (status 404 for unknown content)
     */
    public function show(int $id): array;

    /**
     * Deletes the stored files of contents that have no row any more (left by an
     * interrupted import or delete), in this tenant's storage only.
     *
     * @return array{contentIds: string[], files: int}
     * @throws H5PServiceException
     */
    public function deleteOrphans(): array;
}
