<?php

namespace EscolaLms\CoursesImportExport\Strategies;

use EscolaLms\CoursesImportExport\Strategies\Contract\TopicImportStrategy;
use EscolaLms\HeadlessH5P\Repositories\Contracts\H5PContentRepositoryContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class H5PTopicTypeStrategy implements TopicImportStrategy
{
    private H5PContentRepositoryContract $h5PContentRepository;

    public function __construct()
    {
        $this->h5PContentRepository = app(H5PContentRepositoryContract::class);
    }

    public function make(string $path, array $data): ?int
    {
        $filePath = $path . DIRECTORY_SEPARATOR . $data['h5p_file'];
        if (!File::exists($filePath)) {
            return null;
        }

        $file = new UploadedFile(
            $filePath,
            'export.h5p',
            null,
            null,
            true
        );

        $h5p = $this->h5PContentRepository->upload($file);
        return $h5p ? $h5p->getKey() : null;
    }
}
