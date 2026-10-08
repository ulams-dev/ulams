<?php

namespace Ulams\HeadlessH5P\Repositories\Contracts;

use Ulams\HeadlessH5P\Models\H5PLibrary;
use Ulams\HeadlessH5P\Models\H5PLibraryLanguage;

interface H5PLibraryLanguageRepositoryContract
{
    public function getTranslation(H5PLibrary $library, string $langCode): ?string;

    public function getTranslationString($translation): string;

    public function update(H5PLibraryLanguage $libraryLanguage, H5PLibrary $library, string $languageCode): H5PLibraryLanguage;

    public function create(H5PLibrary $library, string $languageCode): ?H5PLibraryLanguage;

    public function createDefaults(H5PLibrary $library, string $languageCode, string $translation): ?H5PLibraryLanguage;
}
