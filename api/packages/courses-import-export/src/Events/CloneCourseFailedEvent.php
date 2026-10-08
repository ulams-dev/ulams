<?php

namespace EscolaLms\CoursesImportExport\Events;

use EscolaLms\Courses\Models\Course;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CloneCourseFailedEvent extends CloneCourseEvent
{
}
