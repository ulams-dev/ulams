<?php

namespace Ulams\Interactive\Services\Contracts;

use Illuminate\Http\UploadedFile;
use Ulams\Interactive\Models\InteractivePackage;

interface InteractivePackageServiceContract
{
    /** Creates a package with version 1 from an uploaded zip. */
    public function create(UploadedFile $zip, ?string $title, ?int $authorId, ?string $note = null): InteractivePackage;

    /** Adds the next immutable version and makes it current. */
    public function addVersion(InteractivePackage $package, UploadedFile $zip, ?int $authorId, ?string $note = null): InteractivePackage;

    public function rename(InteractivePackage $package, string $title): InteractivePackage;

    /** Deletes the package, its versions and all stored files. Topics must not reference it. */
    public function delete(InteractivePackage $package): void;
}
