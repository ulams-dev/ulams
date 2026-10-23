<?php

namespace Ulams\Commerce\Services;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Ulams\Commerce\Models\AccessPass;
use Ulams\Commerce\Models\Bundle;
use Ulams\Commerce\Support\SellableInfo;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;

/**
 * Reads the sellables of this tenant: a course, a bundle, or an access pass on either. The only place the commerce
 * package touches LMS course data, so a provider receives a plain `ProductSpec` and never reads LMS tables.
 */
class SellableCatalogue
{
    public function exists(SellableRef $ref): bool
    {
        try {
            $this->describe($ref);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** @throws InvalidArgumentException when the sellable does not exist in this tenant */
    public function describe(SellableRef $ref): SellableInfo
    {
        return match ($ref->type) {
            SellableRef::COURSE => $this->course($ref->id),
            SellableRef::BUNDLE => $this->bundle($ref->id),
            SellableRef::ACCESS_PASS => $this->pass($ref->id),
        };
    }

    private function course(int $id): SellableInfo
    {
        $class = $this->courseClass();
        /** @var Model|null $course */
        $course = $class::query()->find($id);
        if ($course === null) {
            throw new InvalidArgumentException("Course {$id} does not exist.");
        }

        // the cart's productable contract names things getName()/getDescription(); a plain course has title/summary
        $name = method_exists($course, 'getName') ? $course->getName() : $course->getAttribute('title');
        $description = method_exists($course, 'getDescription') ? $course->getDescription() : $course->getAttribute('summary');

        return new SellableInfo(mb_substr(trim((string) $name) !== '' ? (string) $name : "Course {$id}", 0, 191), $description === null ? null : (string) $description, [(int) $course->getKey()], $this->isPublished($course));
    }

    private function bundle(int $id): SellableInfo
    {
        $bundle = Bundle::query()->find($id) ?? throw new InvalidArgumentException("Bundle {$id} does not exist.");
        $ids = $bundle->courseIds();
        $courses = $this->courseClass()::query()->whereKey($ids)->get();
        $published = $ids !== [] && $courses->count() === count($ids) && $courses->every(fn (Model $c) => $this->isPublished($c));

        return new SellableInfo($bundle->name, $bundle->description, $ids, $published);
    }

    private function pass(int $id): SellableInfo
    {
        $pass = AccessPass::query()->find($id) ?? throw new InvalidArgumentException("Access pass {$id} does not exist.");
        $subject = $pass->subject_type === SellableRef::BUNDLE ? $this->bundle($pass->subject_id) : $this->course($pass->subject_id);

        return new SellableInfo($pass->name, $subject->description, $subject->courseIds, $subject->published, $pass->days);
    }

    /** @return class-string<Model> */
    private function courseClass(): string
    {
        $configured = config('commerce.sellables.course');

        return is_string($configured) && $configured !== '' ? $configured : Course::class;
    }

    private function isPublished(Model $course): bool
    {
        // a productable that is not a course (the cart test mocks) has no status and is always on sale
        $status = $course->getAttribute('status');

        return $status === null || $status === CourseStatusEnum::PUBLISHED;
    }
}
