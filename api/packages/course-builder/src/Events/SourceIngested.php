<?php

namespace Ulams\CourseBuilder\Events;

use Ulams\CourseBuilder\Models\Source;

/**
 * A source was converted and its fragments written (first upload of a builder session). Other
 * packages react to it: Living Course records revision 1 of the source.
 */
final class SourceIngested
{
    public function __construct(public readonly Source $source)
    {
    }
}
