<?php

namespace Ulams\CourseBuilder\Events;

use Ulams\CourseBuilder\Models\Session;

/** An element was changed by an approved chat edit (other packages mark work based on the old text as out of date). */
final class ElementPatched
{
    public function __construct(public readonly Session $session, public readonly string $elementId)
    {
    }
}
