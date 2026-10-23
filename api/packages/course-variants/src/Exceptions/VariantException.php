<?php

namespace Ulams\CourseVariants\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** A refused routing action, with the HTTP status the API answers. The message is safe to show to the learner. */
final class VariantException extends HttpException
{
    public static function notEnrolled(): self
    {
        return new self(403, 'You need access to this course before you can choose a variant.');
    }

    public static function locked(): self
    {
        return new self(403, 'Your organisation chose this variant for you; ask an administrator to change it.');
    }

    public static function notAVariant(): self
    {
        return new self(422, 'That course is not a variant of this course.');
    }

    public static function unavailable(): self
    {
        return new self(422, 'That variant is not available.');
    }

    public static function invalid(string $message): self
    {
        return new self(422, $message);
    }
}
