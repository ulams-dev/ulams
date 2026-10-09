<?php

namespace Ulams\TopicTypeLayout\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Ulams\TopicTypeLayout\Services\LayoutDocumentValidator;

/** A layout document (a list, or the same list as a JSON string) must match the learner layout manifest (see LayoutDocumentValidator). */
class ValidLayoutDocument implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                $fail("The {$attribute} must be a JSON list of layout nodes.");

                return;
            }
            $value = $decoded;
        }
        foreach (app(LayoutDocumentValidator::class)->validate($value, 10) as $error) {
            $fail("The {$attribute} is not a valid layout: {$error}");
        }
    }
}
