<?php

namespace EscolaLms\TemplatesEmail\TopicTypes;

class TopicTypeChangedVariables extends TopicTypeVariables
{
    //TODO
    public static function assignableClass(): ?string
    {
        return '';
    }

    public static function requiredVariables(): array
    {
        return [];
    }

    public static function requiredVariablesInSection(string $sectionKey): array
    {
        return [];
    }

    public static function defaultSectionsContent(): array
    {
        return [
            'title' => '',
            'content' => ''
        ];
    }
}
