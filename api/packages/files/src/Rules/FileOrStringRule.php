<?php

namespace EscolaLms\Files\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileOrStringRule implements Rule
{
    private array $fileRules;
    private ?string $pathPrefix;
    private string $message;

    public function __construct(array $fileRules, string $pathPrefix = null)
    {
        $this->fileRules = $fileRules;
        $this->pathPrefix = $pathPrefix;
    }

    public function passes($attribute, $value): bool
    {
        if (is_a($value, UploadedFile::class) && !$this->isValidFile($attribute, $value)) {
            return false;
        }

        if (is_string($value)) {
            if (!Storage::exists($value)) {
                $this->message = __('File not exist');
                return false;
            }

            if ($this->pathPrefix && !str_starts_with($value, $this->pathPrefix)) {
                $this->message = __('Path must start with :pathPrefix', ['pathPrefix' => $this->pathPrefix]);
                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        return $this->message;
    }

    private function isValidFile($attribute, $value): bool
    {
        $validator = Validator::make(
            [
                $attribute => $value,
            ],
            [
                $attribute => $this->fileRules,
            ]
        );

        if ($validator->fails()) {
            $this->message = implode(',', $validator->getMessageBag()->get($attribute));

            return false;
        }

        return true;
    }
}
