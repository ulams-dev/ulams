<?php

namespace Ulams\AuthoringMetrics\Services;

use Ulams\AuthoringMetrics\Contracts\SubjectCourseResolver;

/** The resolvers by subject type; other packages add theirs from their service provider. */
class SubjectResolvers
{
    public const BUILD = 'build';

    public const MAINTENANCE = 'maintenance';

    /** @var array<string,SubjectCourseResolver> */
    private array $resolvers = [];

    public function register(SubjectCourseResolver $resolver): void
    {
        $this->resolvers[$resolver->subjectType()] = $resolver;
    }

    /** @return array<string,SubjectCourseResolver> */
    public function all(): array
    {
        return $this->resolvers;
    }
}
