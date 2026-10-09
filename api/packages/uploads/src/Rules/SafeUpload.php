<?php

namespace Ulams\Uploads\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\UploadGuard;

/**
 * Validation rule: `'zip' => ['required', 'file', new SafeUpload('scorm')]`.
 * The failure message says what was wrong (zip-slip path, size, type, virus, ...).
 */
class SafeUpload implements ValidationRule
{
    public function __construct(private readonly string $kind)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            $fail('The :attribute must be an uploaded file.');

            return;
        }

        try {
            app(UploadGuard::class)->check($value, $this->kind);
        } catch (UploadRejected $e) {
            $fail($e->getMessage());
        }
    }
}
