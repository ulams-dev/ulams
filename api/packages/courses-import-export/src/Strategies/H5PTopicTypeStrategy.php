<?php

namespace Ulams\CoursesImportExport\Strategies;

use Illuminate\Support\Facades\File;
use Ulams\CoursesImportExport\Strategies\Contract\TopicImportStrategy;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;

class H5PTopicTypeStrategy implements TopicImportStrategy
{
    private H5PServiceClientContract $h5pServiceClient;

    public function __construct()
    {
        $this->h5pServiceClient = app(H5PServiceClientContract::class);
    }

    /**
     * Imports the exported .h5p package through the H5P service.
     *
     * @return int|null new h5p.contents id
     */
    public function make(string $path, array $data): ?int
    {
        if (empty($data['h5p_file'])) {
            return null;
        }
        $filePath = $path . DIRECTORY_SEPARATOR . $data['h5p_file'];
        if (!File::exists($filePath)) {
            return null;
        }

        return $this->h5pServiceClient->upload($filePath);
    }
}
