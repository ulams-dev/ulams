<?php

namespace Ulams\Interactive\Services\Contracts;

use Illuminate\Http\UploadedFile;
use Ulams\Interactive\Models\InteractivePackage;

interface InteractivePackageServiceContract
{
    /**
     * Creates a package with version 1 from an uploaded zip. A manifest that lists `network` origins is
     * accepted only when `$networkConfirmed` is true; otherwise a 422 names the origins to confirm.
     */
    public function create(UploadedFile $zip, ?string $title, ?int $authorId, ?string $note = null, bool $networkConfirmed = true): InteractivePackage;

    /** Adds the next immutable version and makes it current. */
    public function addVersion(InteractivePackage $package, UploadedFile $zip, ?int $authorId, ?string $note = null, bool $networkConfirmed = true): InteractivePackage;

    public function rename(InteractivePackage $package, string $title): InteractivePackage;

    /** Deletes the package, its versions and all stored files. Topics must not reference it. */
    public function delete(InteractivePackage $package): void;
}
