<?php

namespace EscolaLms\Translations\Services\Contracts;

interface TranslationServiceContract
{
    public function retrieve(string $key, array $replace): array;
}