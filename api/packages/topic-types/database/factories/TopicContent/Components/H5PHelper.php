<?php

namespace Ulams\TopicTypes\Database\Factories\TopicContent\Components;

use Ulams\H5P\Database\Factories\H5PContentFactory;
use Ulams\H5P\Models\H5PContent;

class H5PHelper
{
    /**
     * Test data: inserts a row into h5p.contents from the mock package (no H5P service call).
     */
    public static function createH5PContent(?string $package = null, array $attributes = []): H5PContent
    {
        return H5PContentFactory::fromPackage($package ?? realpath(__DIR__ . '/../../../mocks/hp5.h5p'), $attributes);
    }
}
