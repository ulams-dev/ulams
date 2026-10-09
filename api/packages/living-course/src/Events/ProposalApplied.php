<?php

namespace Ulams\LivingCourse\Events;

use Ulams\LivingCourse\Models\Proposal;

/** An update proposal was applied to the course (progress rules and notifications follow). */
final class ProposalApplied
{
    public function __construct(public readonly Proposal $proposal)
    {
    }
}
