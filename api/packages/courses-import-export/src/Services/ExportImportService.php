<?php

namespace EscolaLms\CoursesImportExport\Services;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Categories\Repositories\Contracts\CategoriesRepositoryContract;
use EscolaLms\Courses\Http\Requests\CreateTopicAPIRequest;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Repositories\Contracts\CourseRepositoryContract;
use EscolaLms\Courses\Repositories\Contracts\LessonRepositoryContract;
use EscolaLms\Courses\Repositories\Contracts\TopicRepositoryContract;
use EscolaLms\Courses\Repositories\Contracts\TopicResourceRepositoryContract;
use EscolaLms\CoursesImportExport\Http\Resources\CourseExportResource;
use EscolaLms\CoursesImportExport\Models\Course;
use EscolaLms\CoursesImportExport\Services\Contracts\ExportImportServiceContract;
use EscolaLms\CoursesImportExport\Strategies\Contract\TopicImportStrategy;
use EscolaLms\CoursesImportExport\Strategies\RichTextTopicTypeStrategy;
use EscolaLms\CoursesImportExport\Strategies\ScormScoTopicTypeStrategy;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ZanySoft\Zip\Facades\Zip;
use ZipArchive;

class ExportImportService implements ExportImportServiceContract
{
    private CourseRepositoryContract $courseRepository;
    private LessonRepositoryContract $lessonRepository;
    private TopicRepositoryContract $topicRepository;
    private TopicResourceRepositoryContract $topicResourceRepo;
    private CategoriesRepositoryContract $categoriesRepository;

    private string $dirFullPath;

    private array $topicTypes = [
        'EscolaLms\\TopicTypes\\Models\\TopicContent\\ScormSco',
        'EscolaLms\\TopicTypes\\Models\\TopicContent\\H5P',
    ];

    public function __construct(
        CourseRepositoryContract        $courseRepository,
        LessonRepositoryContract        $lessonRepository,
        TopicRepositoryContract         $topicRepository,
        TopicResourceRepositoryContract $topicResourceRepository,
        CategoriesRepositoryContract    $categoriesRepository
    )
    {
        $this->courseRepository = $courseRepository;
        $this->lessonRepository = $lessonRepository;
        $this->topicRepository = $topicRepository;
        $this->topicResourceRepo = $topicResourceRepository;
        $this->categoriesRepository = $categoriesRepository;
    }

    private function fixAllPathsBeforeZipping(int $courseId): void
    {
        $course = Course::findOrFail($courseId);
        $course->fixAssetPaths();
    }

    private function createExportJson(\EscolaLms\Courses\Models\Course $course, $dirName): void
    {
        $program = CourseExportResource::make($course);

        $json = json_encode($program);

        Storage::put($dirName . '/content/content.json', $json);
    }

    private function copyCourseFilesToExportFolder(int $courseId): string
    {
        $dirName = 'exports/courses/' . $courseId;

        if (Storage::exists($dirName)) {
            Storage::deleteDirectory($dirName);
        }

        Storage::makeDirectory($dirName);

        $dirFrom = 'course/' . $courseId;
        $dirTo = 'exports/courses/' . $courseId . '/content';
        $fromFiles = Storage::allFiles($dirFrom);

        foreach ($fromFiles as $fromFile) {
            $toFile = str_replace($dirFrom, $dirTo, $fromFile);
            Storage::copy($fromFile, $toFile);
        }

        return $dirName;
    }

    private function createZipFromFolder($dirName, bool $withUrl = true): string
    {
        $filename = uniqid(rand(), true) . '.zip';
        $zip = new ZipArchive();

        if (!Storage::disk('local')->exists($dirName)) {
            Storage::disk('local')->makeDirectory($dirName);
        }

        $zipFile = Storage::disk('local')->path($dirName . DIRECTORY_SEPARATOR . $filename);

        if (!$zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new \Exception("Zip file could not be created: " . $zip->getStatusString());
        }

        $files = Storage::allFiles($dirName . '/content');
        foreach ($files as $file) {
            $content = Storage::get($file);
            $dir = str_replace($dirName . '/content', '', $file);

            if (!$zip->addFromString($dir, $content)) {
                throw new \Exception("File [`{$file}`] could not be added to the zip file: " . $zip->getStatusString());
            }
        }

        $zip->close();

        Storage::deleteDirectory($dirName . '/content');

        if (Storage::getDefaultDriver() === 's3') {
            $s3Path = $dirName . '/' . $filename;
            $fileStream = fopen($zipFile, 'r+');
            Storage::put($s3Path, $fileStream);

            if (is_resource($fileStream)) {
                fclose($fileStream);
            }

            return $withUrl ? Storage::url($s3Path) : $s3Path;
        }

        return $withUrl ? $this->getExportUrl($dirName, $filename) : $this->getExportDir($dirName, $filename);
    }

    private function getExportUrl(string $dirName, string $fileName): string
    {
        return Storage::disk('local')->url($dirName . DIRECTORY_SEPARATOR . $fileName);
    }

    private function getExportDir(string $dirName, string $fileName): string
    {
        return Storage::disk('local')->path($dirName . DIRECTORY_SEPARATOR . $fileName);
    }

    public function export($courseId, bool $withUrl = true): string
    {
        $this->fixAllPathsBeforeZipping($courseId);
        $dirName = $this->copyCourseFilesToExportFolder($courseId);

        $course = \EscolaLms\Courses\Models\Course::with(['lessons.topics.topicable', 'scormSco', 'categories', 'tags'])
            ->findOrFail($courseId);
        $this->createExportJson($course, $dirName);

        return $this->createZipFromFolder($dirName, $withUrl);
    }

    public function import(UploadedFile $zipFile): Model
    {
        $dirPath = 'imports' . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . uniqid(rand(), true);
        $dirFullPath = $this->dirFullPath = $this->extractZipFile($zipFile, $dirPath);
        try {
            $content = json_decode(File::get($dirFullPath . DIRECTORY_SEPARATOR . 'content.json'), true);
            $course = DB::transaction(function () use ($content, $dirFullPath) {
                return $this->createCourseFromImport($content, $dirFullPath);
            });
        } catch (Exception $e) {
            $message = '[' . self::class . '] ' . $e->getMessage();
            Log::error($message);
            throw new Exception($message);
        } finally {
            if (Storage::disk('local')->exists($dirPath)) {
                Storage::disk('local')->deleteDirectory($dirPath);
            }
        }

        return $course->load('categories', 'tags', 'lessons', 'lessons.topics', 'lessons.topics.topicable');
    }

    private function extractZipFile(UploadedFile $zipFile, string $dirPath): string
    {
        if (Storage::disk('local')->exists($dirPath)) {
            Storage::disk('local')->deleteDirectory($dirPath);
        }

        $dirFullPath = Storage::disk('local')->path($dirPath);
        $zip = Zip::open($zipFile);
        $zip->extract($dirFullPath);
        $zip->close();

        return $dirFullPath;
    }

    private function createCategories(array $categories): array
    {
        $ids = [];
        foreach ($categories as $category) {
            $model = $this->createCategory($category);
            $ids[] = $model->getKey();
        }

        return Arr::flatten($ids);
    }

    private function createCategory(array $category): Model
    {
        $foundCategory = Category::whereSlug($category['slug'])->first();
        if ($foundCategory) {
            return $foundCategory;
        }

        $filePath = $this->dirFullPath . DIRECTORY_SEPARATOR . $category['icon'];
        if (File::exists($filePath)) {
            $file = new HttpFile($filePath);
            $category['icon'] = Storage::putFile('categories', $file, 'public');
        }

        if ($category['parent']) {
            $parent = $this->createCategory($category['parent']);
            $category['parent_id'] = $parent->getKey();
        }

        unset($category['parent']);
        return $this->categoriesRepository->create($category);
    }

    private function createCourseFromImport(array $courseData, string $dirFullPath): Model
    {
        unset($courseData['author_id']);

        $courseData = $this->addFilesToArrayBasedOnPath($courseData, $dirFullPath);

        if (isset($courseData['scorm_sco'])) {
            $strategy = new ScormScoTopicTypeStrategy();
            $courseData['scorm_sco_id'] = $strategy->make($dirFullPath, $courseData['scorm_sco']);
        }

        $courseValidator = Validator::make($courseData, Course::rules());
        /** @var Course $course */
        $course = $this->courseRepository->create($courseValidator->validate());

        // create categories
        if (isset($courseData['categories'])) {
            $categoryIds = $this->createCategories($courseData['categories']);
            $course->categories()->sync($categoryIds);
        }

        // create tags
        if (isset($courseData['tags'])) {
            $tags = array_map(function ($tag) {
                return ['title' => $tag['title']];
            }, $courseData['tags']);
            $course->tags()->createMany($tags);
        }

        if (isset($courseData['lessons']) && is_array($courseData['lessons'])) {
            foreach ($courseData['lessons'] as $lesson) {
                $lesson['course_id'] = $course->getKey();
                $this->createLessonFromImport($lesson, $dirFullPath);
            }
        }

        return $course;
    }

    private function createLessonFromImport(array $lessonData, string $dirFullPath): Model
    {
        $lessonValidator = Validator::make($lessonData, Lesson::$rules);
        /** @var Lesson $lesson */
        $lesson = $this->lessonRepository->create($lessonValidator->validate());

        if (isset($lessonData['topics']) && is_array($lessonData['topics'])) {
            foreach ($lessonData['topics'] as $topic) {
                $topic['lesson_id'] = $lesson->getKey();
                $this->createTopicFromImport(array_filter($topic), $dirFullPath, $lessonData['course_id']);
            }
        }

        if (isset($lessonData['lessons']) && is_array($lessonData['lessons'])) {
            foreach ($lessonData['lessons'] as $child) {
                $child['parent_lesson_id'] = $lesson->getKey();
                $child['course_id'] = $lesson->course_id;
                $this->createLessonFromImport($child, $dirFullPath);
            }
        }

        return $lesson;
    }

    private function createTopicFromImport(array $topicData, string $dirFullPath, int $courseId): ?Model
    {
        if (!isset($topicData['topicable_type'])) {
            return null;
        }

        $topicData = array_merge($topicData, $topicData['topicable'] ?? []);
        unset($topicData['topicable']);

        if (in_array($topicData['topicable_type'], $this->topicTypes)) {
            $strategy = $this->getTopicTypeImportStrategy($topicData['topicable_type']);
            $topicData['value'] = $strategy->make($dirFullPath, $topicData);
        }

        $request = new CreateTopicAPIRequest($topicData);
        $request->setValidator(Validator::make($topicData, $request->rules()));

        foreach (['value', 'poster'] as $key) {
            if (isset($topicData[$key]) && File::exists($dirFullPath . DIRECTORY_SEPARATOR . $topicData[$key])) {
                $request->files->add([
                    $key => new UploadedFile(
                        $dirFullPath . DIRECTORY_SEPARATOR . $topicData[$key],
                        $topicData[$key],
                        null,
                        null,
                        true
                    )
                ]);
            }
        }

        $topic = $this->topicRepository->createFromRequest($request);

        if ($topicData['topicable_type'] === 'EscolaLms\\TopicTypes\\Models\\TopicContent\\RichText'
            && array_key_exists('asset_folder', $topicData)) {
            $this->handleRichTextTopicImport($topic, $dirFullPath, $topicData, $courseId);
        }
        if (isset($topicData['resources']) && is_array($topicData['resources'])) {
            $this->createTopicResources($topicData['resources'], $topic, $dirFullPath);
        }
        return $topic;
    }

    private function createTopicResources(array $resources, Topic $topic, string $dirFullPath): array
    {
        $createdResources = [];
        foreach ($resources as $resource) {
            if (isset($resource['path'])
                && isset($resource['name'])
                && File::exists($dirFullPath . DIRECTORY_SEPARATOR . $resource['path'])
            ) {
                $createdResources[] = $this->topicResourceRepo->storeUploadedResourceForTopic(
                    $topic,
                    new UploadedFile($dirFullPath . DIRECTORY_SEPARATOR . $resource['path'], $resource['name'])
                );
            }
        }

        return $createdResources;
    }

    private function addFilesToArrayBasedOnPath(array $data, string $dirFullPath): array
    {
        foreach ($data as $key => $value) {
            if (Str::endsWith($key, '_path')
                && File::exists($dirFullPath . DIRECTORY_SEPARATOR . $value)
                && !File::isDirectory($dirFullPath . DIRECTORY_SEPARATOR . $value)
            ) {
                $fileKey = Str::before($key, '_path');
                $data[$fileKey] = new UploadedFile(
                    $dirFullPath . DIRECTORY_SEPARATOR . $value,
                    $value,
                    null,
                    null,
                    true
                );
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function getTopicTypeImportStrategy(string $topicType): TopicImportStrategy
    {
        $strategy = 'EscolaLms\\CoursesImportExport\\Strategies\\' . substr(strrchr($topicType, "\\"), 1) . 'TopicTypeStrategy';
        return new $strategy();
    }

    private function handleRichTextTopicImport(Topic $topic, string $zipFilesPath, array $data, $courseId): void
    {
        $destinationPath = $this->getTopicAssetsDestinationPath($topic, $courseId);

        $this->updateRichTextTopicableValue($topic, $destinationPath);
        $this->importRichTextTopicAssets($zipFilesPath, $destinationPath, $data['asset_folder']);
    }

    private function importRichTextTopicAssets(string $filesPath, string $destinationPath, string $assetFolder): void
    {
        $topicAssetsPath =
            $filesPath
            . DIRECTORY_SEPARATOR
            . 'topic'
            . DIRECTORY_SEPARATOR
            . $assetFolder
            . DIRECTORY_SEPARATOR;

        if (!is_dir($topicAssetsPath)) {
            return;
        }

        $files = array_diff(scandir($topicAssetsPath), array('.', '..'));
        foreach ($files as $file) {
            $fileFullPath = $topicAssetsPath . DIRECTORY_SEPARATOR . $file;
            if (!is_file($fileFullPath)) {
                continue;
            }

            $fileToStore = file_get_contents($fileFullPath);
            Storage::put($destinationPath . basename($file), $fileToStore);
        }
    }

    private function updateRichTextTopicableValue(Topic $topic, $path): void
    {
        $topicable = $topic->topicable;
        //api images
        // @phpstan-ignore-next-line
        $topicable->value = preg_replace_callback(
            '/\!\[\]\((course\/.*?\.\w+)\)/',
            function ($matches) use ($path) {
                return '![](' . url('api/images/img') . '?path=' . $path . basename($matches[1]) . '&w=1000)';
            },
            // @phpstan-ignore-next-line
            $topicable->value
        );
        //other assets
        $topicable->value = preg_replace_callback(
            '/!\[(course.*?)\]\(\1\)/',
            function ($matches) use ($path) {
                return '![' . url('storage/' . $path) . '/' . basename($matches[1]) . '](' . url('storage/' . $path ) . '/' . basename($matches[1]) . ')';
            },
            $topicable->value
        );
        $topicable->save();
    }

    private function getTopicAssetsDestinationPath(Topic $topic, int $courseId): string
    {
        return "course/$courseId/topic/{$topic->getKey()}/";
    }
}
