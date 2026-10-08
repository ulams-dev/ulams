<?php

namespace EscolaLms\CoursesImportExport\Models;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Courses\Models\Course as BaseCourse;
use EscolaLms\CoursesImportExport\Enums\CoursesImportExportEnum;
use EscolaLms\Scorm\Services\Contracts\ScormServiceContract;
use Illuminate\Support\Facades\Storage;

class Course extends BaseCourse
{
    private function fixPath($key): array
    {
        $value =  'public/' . $this->$key;
        $destination = sprintf('course/%d/%s', $this->id, basename($value));

        if (strpos($value, $destination) === false && Storage::exists($value)) {
            $result = [$value, $destination];
            if (!Storage::exists($destination)) {
                Storage::move($value, $destination);
            }

            $this->$key = $destination;
            $this->save();

            return $result;
        }

        return [];
    }

    public function fixScormAssetPath(): array
    {
        if (!$this->scormSco) {
            return [];
        }

        // @phpstan-ignore-next-line
        $scorm = $this->scormSco->scorm;
        $destination = sprintf('course/%d/%s', $this->id, basename(CoursesImportExportEnum::SCORM_FILE));

        $scormService = app(ScormServiceContract::class);
        $scormZipPath = $scormService->zipScorm($scorm->getKey());

        if (Storage::exists($destination)) {
            Storage::delete($destination);
        }

        $inputStream = Storage::disk('local')->getDriver()->readStream($scormZipPath);
        Storage::getDriver()->writeStream($destination, $inputStream);

        return $destination ? [$destination] : [];
    }

    public function fixCategoriesAssetPath(): array
    {
        if (!$this->categories) {
            return [];
        }

        $categories = $this->categories;

        $destinations = [];
        foreach ($categories as $category) {
            $destinations[] = $this->fixCategoryPath($category);
        }

        return $destinations;
    }

    public function fixCategoryPath(Category $category): string
    {
        if ($category->parent) {
            $this->fixCategoryPath($category->parent);
        }

        $destination = sprintf('course/%d/%s', $this->id, $category->icon);
        if (!Storage::exists($destination) && Storage::exists($category->icon)) {
            Storage::copy($category->icon, $destination);
        }

        return $destination;
    }


    public function fixAssetPaths(): array
    {
        $results = [];
        $results = $results + $this->fixPath('image_path');
        $results = $results + $this->fixPath('video_path');
        $results = $results + $this->fixPath('poster_path');
        $results = $results + $this->fixScormAssetPath();
        $results = $results + $this->fixCategoriesAssetPath();

        foreach ($this->lessons as $lesson) {
            foreach ($lesson->topics as $topic) {
                $topicable = $topic->topicable;
                if (isset($topicable)) {
                    // @phpstan-ignore-next-line
                    foreach ($topic->topicable->fixAssetPaths() as $fix) {
                        $results = $results + $fix;
                    }
                }

                foreach ($topic->resources as $resource) {
                    $results = $results + $resource->fixAssetPaths();
                }
            }
        }

        return $results;
    }
}
