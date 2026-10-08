<?php

namespace EscolaLms\Cmi5\Rules;

use EscolaLms\Cmi5\Enums\Cmi5Enum;
use Illuminate\Contracts\Validation\Rule;
use ZipArchive;

class Cmi5Rule implements Rule
{
    public function passes($attribute, $value): bool
    {
        $zip = new ZipArchive();

        if (!$zip->open($value) || !$zip->getFromName(Cmi5Enum::MANIFEST_FILE)) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'Invalid cmi5 file.';
    }
}
