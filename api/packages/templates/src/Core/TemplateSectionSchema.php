<?php

namespace EscolaLms\Templates\Core;

use ArrayAccess;
use EscolaLms\Templates\Enums\TemplateSectionTypeEnum;

class TemplateSectionSchema implements ArrayAccess
{
    private string $key;
    private TemplateSectionTypeEnum $type;
    private bool $required = false;
    private bool $readonly = false;

    public function __construct(string $key, TemplateSectionTypeEnum $type, bool $required = false, bool $readonly = false)
    {
        $this->key = $key;
        $this->type = $type;
        $this->required = $required;
        $this->readonly = $readonly;
    }

    public function offsetExists($offset): bool
    {
        return property_exists($this, $offset);
    }

    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->{$offset};
        }
        return null;
    }

    public function offsetSet($offset, $value): void
    {
        return;
    }

    public function offsetUnset($offset): void
    {
        return;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): TemplateSectionTypeEnum
    {
        return $this->type;
    }

    public function getRequired(): bool
    {
        return $this->required;
    }

    public function getReadonly(): bool
    {
        return $this->readonly;
    }
}
