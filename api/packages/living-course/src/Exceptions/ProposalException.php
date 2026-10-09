<?php

namespace Ulams\LivingCourse\Exceptions;

use RuntimeException;

/** A refused action on a proposal, with the HTTP status and a message safe to show the author. */
class ProposalException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly array $extra = [])
    {
        parent::__construct($message);
    }
}
