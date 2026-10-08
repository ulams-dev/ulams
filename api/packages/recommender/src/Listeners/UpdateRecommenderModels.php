<?php

namespace EscolaLms\Recommender\Listeners;

use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Settings\Events\SettingPackageConfigUpdated;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UpdateRecommenderModels
{
    private const COURSE_KEY = 'course';

    private const EXERCISE_KEY = 'exercise';

    public function handle(SettingPackageConfigUpdated $event): void
    {
        if (!config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.api_url')) {
            return;
        }

        $courseModel = Arr::get($event->getConfig(), EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.course_model.value');
        $exerciseModel = Arr::get($event->getConfig(), EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.exercise_model.value');

        if ($courseModel && $this->updateModel($courseModel, self::COURSE_KEY)) {
            Http::post(
                config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.api_url') . 'course/model',
                ['file_content' => $courseModel]
            );
        }
        if ($exerciseModel && $this->updateModel($exerciseModel, self::EXERCISE_KEY)) {
            Http::post(
                config(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.api_url') . 'exercise/model',
                ['file_content' => $exerciseModel]
            );
        }
    }

    private function updateModel(string $model, string $modelType): bool
    {
        $key = 'recommender.' . $modelType . '_model_hash';
        $hash = hash('sha256', $model);

        if (!Cache::get($key) || Cache::get($key) !== $hash) {
            Cache::put($key, $hash);
            return true;
        }

        return false;
    }
}
