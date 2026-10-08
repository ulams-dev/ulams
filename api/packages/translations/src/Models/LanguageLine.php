<?php

namespace Ulams\Translations\Models;

use Ulams\Translations\Database\Factories\LanguageLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\TranslationLoader\LanguageLine as LanguageLineCore;

class LanguageLine extends LanguageLineCore
{
    use HasFactory;

    protected $casts = [
        'text' => 'array',
        'public' => 'boolean'
    ];

    protected static function newFactory(): LanguageLineFactory
    {
        return LanguageLineFactory::new();
    }
}
