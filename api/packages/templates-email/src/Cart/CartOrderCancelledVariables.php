<?php

namespace EscolaLms\TemplatesEmail\Cart;

class CartOrderCancelledVariables extends CartVariables
{

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
