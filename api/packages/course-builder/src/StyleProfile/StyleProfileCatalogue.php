<?php

namespace Ulams\CourseBuilder\StyleProfile;

use Ulams\CourseBuilder\Publish\LandingValidator;

/** The names of the approved page components a style profile may prefer or disallow (the page catalogue manifest). */
final class StyleProfileCatalogue
{
    /** @var string[]|null */
    private ?array $components = null;

    /** @return string[] */
    public function components(): array
    {
        return $this->components ??= array_keys((array) (json_decode((string) file_get_contents(LandingValidator::manifestPath()), true)['components'] ?? []));
    }
}
