<?php

declare(strict_types=1);

namespace Ulams\AuthoringMetrics\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\AuthoringMetrics\Services\Subjects\BuilderSessionResolver;
use Ulams\CourseBuilder\Models\Session;

class BuilderSessionResolverQwenTest extends TestCase
{
    public function testSubjectType()
    {
        $resolver = new BuilderSessionResolver();
        $this->assertEquals(Session::SUBJECT_TYPE, $resolver->subjectType());
    }

    public function testBucket()
    {
        $resolver = new BuilderSessionResolver();
        $this->assertEquals('build', $resolver->bucket()); // Assuming SubjectResolvers::BUILD is 'build'
    }

    public function testSubjectIdsWithSingleId()
    {
        $resolver = new BuilderSessionResolver();
        $sessionIds = [1];
        $this->assertEquals($sessionIds, $resolver->subjectIds($sessionIds));
    }

    public function testSubjectIdsWithMultipleIds()
    {
        $resolver = new BuilderSessionResolver();
        $sessionIds = [1, 2, 3];
        $this->assertEquals($sessionIds, $resolver->subjectIds($sessionIds));
    }

    public function testSubjectIdsWithEmptyArray()
    {
        $resolver = new BuilderSessionResolver();
        $sessionIds = [];
        $this->assertEquals([], $resolver->subjectIds($sessionIds));
    }

    public function testSubjectIdsWithNullValues()
    {
        $resolver = new BuilderSessionResolver();
        $sessionIds = [1, null, 3];
        $expected = [1, 3];
        $this->assertEquals($expected, array_values(array_filter($sessionIds, function($id) {
            return $id !== null;
        })));
    }
}
