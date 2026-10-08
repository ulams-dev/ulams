<?php

namespace EscolaLms\Categories\Dtos\Traits;

use Illuminate\Support\Str;

trait DtoHelper
{
    protected function setterByData(array $data): void
    {
        foreach ($data as $k => $v) {
            $key = preg_replace_callback('/[_|-]([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $k);
            if (method_exists($this, 'set' . $key)) {
                $this->{'set' . $key}($v);
            } else {
                $key = lcfirst($key);
                $this->$key = $v;
            }
        }
    }

    protected function getterByAttribute(string $attribute)
    {
        $key = Str::studly($attribute);
        if (method_exists($this, 'get' . $key)) {
            return $this->{'get' . $key}();
        }

        return $this->{lcfirst($key)} ?? null;
    }

    protected function fillInArray(array $fillables): array
    {
        $result = [];
        foreach ($fillables as $fill) {
            $value = $this->getterByAttribute($fill);
            if ($value === null) {
                continue;
            }
            $result[$fill] = $value;
        }

        return $result;
    }
}