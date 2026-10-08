<?php

namespace EscolaLms\Templates\Events;

use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Models\User;
use EscolaLms\Courses\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ManuallyTriggeredEvent
{
    use Dispatchable, SerializesModels;

    private User $user;
    private ?Course $course;
    private ?Product $product;

    public function __construct(User $user, Course $course = null, Product $product = null)
    {
        $this->user = $user;
        $this->course = $course;
        $this->product = $product;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }
}
