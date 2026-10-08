<?php

namespace Ulams\Translations\Services\Contracts;

interface TranslationServiceContract
{
    public function retrieve(string $key, array $replace): array;
}