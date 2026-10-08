<?php

namespace EscolaLms\Templates\Core;

use EscolaLms\Settings\Models\Setting;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class SettingsVariables
{
    private static array $settings = [];

    public static function settings(): array
    {
        if (!self::$settings) {
            self::getSettings();
        }

        $result = [];
        foreach (self::$settings as $setting) {
            $key =  Str::ucfirst($setting['key']) .  Str::ucfirst($setting['type']);
            $name = '@GlobalSettings' . Str::ucfirst(Str::camel(preg_replace('/[^a-z0-9]+/i', ' ', ($key))));
            $result[$name] = [
                'key' => $setting['key'],
                'group' => $setting['group'],
                'value' => $setting['value'],
                'type' => $setting['type'],
            ];
        }

        return $result;
    }

    public static function clearSettings(): void
    {
        self::$settings = [];
    }

    public static function getSettingsKeys(): array
    {
        return array_keys(static::settings());
    }

    public static function getSettingsTypes(): array
    {
        return array_map(fn($item) => $item['type'], static::settings());
    }

    public static function getSettingsValues(): array
    {
        $settings = static::settings();

        $result = [];
        foreach ($settings as $key => $setting) {
            $func = $setting['type'] . 'Parser';
            if (method_exists(__CLASS__, $func)) {
                $result[$key] = static::{$func}($setting['value']);
            }
        }

        return $result;
    }

    public static function markdownParser(string $markdown): string
    {
        return Markdown::parse($markdown);
    }

    public static function fileParser(string $path): string
    {
        return Storage::url($path);
    }

    public static function imageParser(string $path): string
    {
        return static::fileParser($path);
    }

    public static function configParser(string $value)
    {
        return config($value);
    }

    public static function textParser(string $text): string
    {
        return $text;
    }

    private static function getSettings(): void
    {
        self::$settings = Setting::where([
            ['public', true],
            ['enumerable', true],
            ['type', '!=', 'json']
        ])
            ->get()
            ->toArray();
    }
}
