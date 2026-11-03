<?php

namespace Ulams\Reporting\Definition;

use RuntimeException;

/** A report definition that is malformed or asks for something its dataset does not offer. */
class InvalidReportDefinition extends RuntimeException
{
    /** @param list<array{path:string,message:string}> $errors JSON pointer paths ("/filters/0/op") and what is wrong */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid report definition: ' . ($errors[0]['path'] ?? '/') . ' ' . ($errors[0]['message'] ?? ''));
    }

    /** Laravel validation messages keyed by dot path ("filters.0.op"). @return array<string, list<string>> */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->errors as $error) {
            $key = trim(str_replace('/', '.', $error['path']), '.');
            $messages[$key === '' ? 'definition' : $key][] = $error['message'];
        }

        return $messages;
    }
}
