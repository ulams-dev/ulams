<?php

namespace Ulams\CourseVariants\Tests\Feature;

use InvalidArgumentException;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Models\VariantGroupMember;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\CourseVariants\Tests\TestCase;

/** Families of variant courses (ADR 0192): grouping, hiding members from the catalogue and describing the family. */
class CourseVariantServiceTest extends TestCase
{
    private function service(): CourseVariantServiceContract
    {
        return $this->app->make(CourseVariantServiceContract::class);
    }

    public function testAMemberJoinsTheFamilyOfTheDefaultCourseAndLeavesTheCatalogue(): void
    {
        $base = $this->publishedCourse('Git basics');
        $expert = $this->publishedCourse('Git basics (Expert)');

        $member = $this->service()->addMember($base->getKey(), $expert->getKey(), 'expert', 'Expert', 'expert', null, 2);

        $this->assertSame('expert', $member->variant_key);
        $this->assertFalse((bool) $expert->refresh()->findable, 'the variant is hidden from the catalogue');
        $this->assertTrue((bool) $base->refresh()->findable, 'the default course stays listed');
        $this->assertSame(1, VariantGroup::query()->count());

        $family = $this->service()->describe($expert->getKey());
        $this->assertFalse($family['isDefault']);
        $this->assertSame($base->getKey(), $family['defaultCourseId']);
        $this->assertSame([['courseId' => $expert->getKey(), 'variantKey' => 'expert', 'label' => 'Expert', 'level' => 'expert', 'role' => null, 'position' => 2]], $family['members']);
        $this->assertTrue($this->service()->describe($base->getKey())['isDefault']);
        $this->assertNull($this->service()->describe($this->publishedCourse('Other')->getKey()));
    }

    public function testTheCatalogueListsTheFamilyOnceThroughItsDefaultCourse(): void
    {
        $base = $this->publishedCourse('Git basics');
        $expert = $this->publishedCourse('Git basics (Expert)');
        $beginner = $this->publishedCourse('Git basics (Beginner)');
        $this->service()->addMember($base->getKey(), $expert->getKey(), 'expert', 'Expert', 'expert', null);
        $this->service()->addMember($base->getKey(), $beginner->getKey(), 'beginner', 'Beginner', 'beginner', null);

        $ids = collect($this->getJson('/api/courses')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertContains($base->getKey(), $ids);
        $this->assertNotContains($expert->getKey(), $ids);
        $this->assertNotContains($beginner->getKey(), $ids);
    }

    public function testAddingAgainUpdatesTheMemberAndNeverDuplicatesIt(): void
    {
        $base = $this->publishedCourse('A');
        $variant = $this->publishedCourse('A (B)');
        $this->service()->addMember($base->getKey(), $variant->getKey(), 'b', 'B', null, 'support', 1);
        $this->service()->addMember($base->getKey(), $variant->getKey(), 'b', 'Support team', null, 'support', 3);

        $this->assertSame(1, VariantGroupMember::query()->count());
        $this->assertSame(1, VariantGroup::query()->count());
        $this->assertSame('Support team', $this->service()->describe($base->getKey())['members'][0]['label']);
        $this->assertSame(3, $this->service()->describe($base->getKey())['members'][0]['position']);
    }

    public function testACourseCannotBelongToTwoFamiliesOrBeItsOwnMember(): void
    {
        $a = $this->publishedCourse('A');
        $b = $this->publishedCourse('B');
        $v = $this->publishedCourse('V');
        $this->service()->addMember($a->getKey(), $v->getKey(), 'v', 'V', 'expert', null);

        try {
            $this->service()->addMember($b->getKey(), $v->getKey(), 'v', 'V', 'expert', null);
            $this->fail('A course in one family cannot join another.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already belongs to another family', $e->getMessage());
        }
        try {
            $this->service()->addMember($a->getKey(), $a->getKey(), 'self', 'Self', null, null);
            $this->fail('The default course is not its own member.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, VariantGroupMember::query()->count());
        }
        try {
            $this->service()->family($v->getKey());
            $this->fail('A member cannot be a default course.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, VariantGroup::query()->count());
        }
    }

    public function testRemovingAMemberListsItInTheCatalogueAgain(): void
    {
        $base = $this->publishedCourse('A');
        $variant = $this->publishedCourse('A (B)');
        $this->service()->addMember($base->getKey(), $variant->getKey(), 'b', 'B', null, 'support');
        $this->assertFalse((bool) $variant->refresh()->findable);

        $this->service()->removeMember($variant->getKey());

        $this->assertTrue((bool) $variant->refresh()->findable);
        $this->assertNull($this->service()->describe($variant->getKey()));
        $this->service()->removeMember($variant->getKey()); // safe to repeat
    }
}
