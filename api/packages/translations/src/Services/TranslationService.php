<?php

namespace EscolaLms\Translations\Services;

use EscolaLms\Translations\Dto\TranslationDto;
use EscolaLms\Translations\Services\Contracts\TranslationServiceContract;
use Illuminate\Support\Arr;

class TranslationService implements TranslationServiceContract
{
    public function retrieve(string $key, array $replace): array
    {
        $result = [];
        $trans = __($key, $replace);

        if (is_string($trans)) {
            $result[] = new TranslationDto($key, $trans);
        } else {
            $trans = Arr::dot($trans);
            $result = array_map(function ($transKey, $transValue) use ($key) {
                return new TranslationDto("$key.$transKey", $transValue);
            }, array_keys($trans), $trans);
        }

        return $result;
    }
}