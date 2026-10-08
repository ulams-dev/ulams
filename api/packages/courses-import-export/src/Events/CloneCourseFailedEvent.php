<?php

namespace Ulams\CoursesImportExport\Events;

use Ulams\Courses\Models\Course;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CloneCourseFailedEvent extends CloneCourseEvent
{
}
