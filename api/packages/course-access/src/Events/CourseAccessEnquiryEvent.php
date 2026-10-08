<?php

namespace EscolaLms\CourseAccess\Events;

use EscolaLms\Core\Models\User;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class CourseAccessEnquiryEvent
{
    use Dispatchable, SerializesModels;

    public User $user;
    public CourseAccessEnquiry $courseAccessEnquiry;

    public function __construct(User $user, CourseAccessEnquiry $courseAccessEnquiry)
    {
        $this->user = $user;
        $this->courseAccessEnquiry = $courseAccessEnquiry;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCourseAccessEnquiry(): CourseAccessEnquiry
    {
        return $this->courseAccessEnquiry;
    }
}
