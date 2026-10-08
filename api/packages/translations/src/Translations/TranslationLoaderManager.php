<?php

namespace EscolaLms\Translations\Translations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\TranslationLoader\LanguageLine;
use Spatie\TranslationLoader\TranslationLoaderManager as TranslationLoaderManagerCore;

class TranslationLoaderManager extends TranslationLoaderManagerCore
{
    public function load($locale, $group, $namespace = null): array
    {
        //Locale sent in header is in format xx-YY, but the folder name is in format xx
        $fileLocale = Str::before($locale, '-');

        try {
            $fileTranslations = parent::load($fileLocale, $group, $namespace);
            if (!is_null($namespace) && $namespace !== '*') {
                return $fileTranslations;
            }

            $loaderTranslations = $this->getTranslationsForTranslationLoaders($locale, $group, $namespace);
            return array_replace_recursive($fileTranslations, $loaderTranslations);
        } catch (QueryException $e) {
            $modelClass = config('translation-loader.model');
            $model = new $modelClass;
            if (is_a($model, LanguageLine::class)) {
                if (!Schema::hasTable($model->getTable())) {
                    return parent::load($fileLocale, $group, $namespace);
                }
            }

            throw $e;
        }
    }
}
