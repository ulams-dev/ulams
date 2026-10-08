<?php

namespace EscolaLms\Files\Http\Services;

use EscolaLms\Auth\Repositories\Contracts\UserRepositoryContract;
use EscolaLms\Core\Models\User;
use EscolaLms\Files\Enums\FilePermissionsEnum;
use EscolaLms\Files\Http\Exceptions\CannotDeleteFile;
use EscolaLms\Files\Http\Exceptions\DirectoryOutsideOfRootException;
use EscolaLms\Files\Http\Exceptions\MoveException;
use EscolaLms\Files\Http\Exceptions\PutAllException;
use EscolaLms\Files\Http\Services\Contracts\FileServiceContract;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileService implements FileServiceContract
{
    private FilesystemAdapter $disk;
    private UserRepositoryContract $userRepository;

    public function __construct(FilesystemManager $manager, UserRepositoryContract $userRepository)
    {
        $this->disk = $manager->disk();
        $this->userRepository = $userRepository;
    }

    private function cleanFilename(UploadedFile $file): string
    {
        return Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
    }

    private function cleanFilenameString(string $filename): string
    {
        return Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
    }

    public function findAll(string $directory, array $list): array
    {
        $ret = [];
        /** @var UploadedFile $file */
        foreach ($list as $file) {
            $name = $this->cleanFilename($file);
            $path = $directory . '/' . $name;

            if ($this->disk->exists($path)) {
                $ret[] = $path;
            }
        }
        return $ret;
    }

    /**
     * @param string $directory
     * @param array $list
     * @throws PutAllException
     * @return array $list
     */
    public function putAll(string $directory, array $list): array
    {
        $paths = [];
        /** @var UploadedFile $file */
        foreach ($list as $file) {
            $path = $this->disk->putFileAs($directory, $file, $this->cleanFilename($file), 'public');
            if ($path === false) {
                throw new PutAllException($file->getClientOriginalName(), $directory);
            }
            $paths[] = $path;
        }

        $results = collect($paths)
            ->map(fn (string $path) => [
                'name' => $path,
                'created_at' => date(DATE_RFC3339),
                'mime' => $this->disk->mimeType($path),
                'url' => $this->disk->url($path),
            ]);

        return $results->toArray();
    }

    /**
     * @param string $directory
     * @return Collection
     */
    public function listInfo(string $directory): Collection
    {
        try {
            $this->isOfBounds($directory);
            $user = auth()->user();

            return collect($this->disk->listContents($directory, false))
                ->filter(fn ($metadata) => $this->checkUserAccessToFile($user, $metadata))
                ->map(fn ($metadata) => $this->metadataToArray($metadata))
                ->sortByDesc('isDir')
                ->values();
        } catch (\LogicException $exception) {
            throw new DirectoryOutsideOfRootException($directory);
        }
    }

    /**
     * @param string $directory
     * @param string $name
     * @return Collection
     */
    public function findByName(string $directory, string $name): Collection
    {
        try {
            $this->isOfBounds($directory);
            $user = auth()->user();

            return collect($this->disk->listContents($directory, true))
                ->filter(fn ($metadata) => $this->checkUserAccessToFile($user, $metadata))
                ->filter(fn ($metadata) => Str::contains($metadata['basename'] ?? basename($metadata['path']), [
                    $name,
                    Str::slug($name),
                    $this->cleanFilenameString($name),
                ]))
                ->map(fn ($metadata) => $this->metadataToArray($metadata))
                ->sortByDesc('isDir')
                ->values();
        } catch (\LogicException $exception) {
            throw new DirectoryOutsideOfRootException($directory);
        }
    }

    /**
     * @param string $url
     * @throws CannotDeleteFile
     * @throws DirectoryOutsideOfRootException
     */
    public function delete(string $url): bool
    {
        $prefix = trim($this->disk->url('/'), '/');

        if (substr($url, 0, strlen($prefix)) === $prefix) {
            $path = substr($url, strlen($prefix));
        } else {
            $path = $url;
        }

        try {
            $this->isOfBounds($path);

            if ($this->disk->exists($path)) {
                if (File::isDirectory($this->disk->path($path))) {
                    $deleted = $this->disk->deleteDirectory($path);
                } else {
                    $deleted = $this->disk->delete($path);
                }
            } else {
                $deleted = false;
            }
            if (!$deleted) {
                throw new CannotDeleteFile($url);
            }
        } catch (\LogicException $e) {
            throw new DirectoryOutsideOfRootException($url);
        }

        return $deleted;
    }

    /**
     * @param string $sourceUrl
     * @param string $destinationUrl
     * @throws MoveException
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function move(string $sourceUrl, string $destinationUrl): bool
    {
        try {
            $ret = $this->disk->move($this->urlToPath($sourceUrl), $this->urlToPath($destinationUrl));
            if (!$ret) {
                throw new MoveException($sourceUrl, $destinationUrl);
            }
        } catch (\League\Flysystem\FileNotFoundException $exception) {
            throw new MoveException($sourceUrl, $destinationUrl);
        }
        return $ret;
    }

    private function urlToPath(string $url): string
    {
        $prefix = $this->disk->url('/');
        if (substr($url, 0, strlen($prefix)) === $prefix) {
            $path = substr($url, strlen($prefix));
        } else {
            $path = $url;
        }
        return $path;
    }

    public function addUserAccessToDirectory(User $user, string $directoryName): array
    {
        $directories = $this->getUserAccessToDirectories($user);
        $directories[] = $directoryName;
        $this->saveUserAccessToDirectory($user, $directories);

        return $directories;
    }

    public function removeUserAccessToDirectory(User $user, string $directoryName): array
    {
        $directories = $this->getUserAccessToDirectories($user);
        $directories = array_values(array_filter($directories, fn($value) => $value !== $directoryName));
        $this->saveUserAccessToDirectory($user, $directories);

        return $directories;
    }

    private function getUserAccessToDirectories(User $user): array
    {
        return isset($user->access_to_directories)
            ? json_decode($user->access_to_directories)
            : [];
    }

    private function saveUserAccessToDirectory(User $user, array $directories): void
    {
        $this->userRepository->update([
            'access_to_directories' => json_encode(array_unique($directories))
        ], $user->getKey());
    }

    private function checkUserAccessToFile(User $user, $metadata): bool
    {
        if ($user->can(FilePermissionsEnum::FILE_LIST, 'api')) {
            return true;
        }

        $accessToDirectories = $this->getUserAccessToDirectories($user);
        $isDir = isset($metadata['type']) && $metadata['type'] === 'dir';

        if ($isDir && count(array_intersect($this->disk->allDirectories($metadata['path']), $accessToDirectories)) > 0) {
            return true;
        }

        return Str::contains($metadata['path'], $accessToDirectories);
    }

    private function isOfBounds(string $path): bool
    {
        $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
        $abs = '/' . trim($path, '/');
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}
        if (preg_match('/\.\.\//', $abs, $o)) {
            throw new \LogicException();
        }
        return true;
    }

    private function metadataToArray($metadata): array
    {
        return [
            'name' => $metadata['basename'] ?? basename($metadata['path']),
            'url' =>  $this->disk->url($metadata['path']),
            'created_at' => isset($metadata['timestamp']) ? date(DATE_RFC3339, $metadata['timestamp']) : (isset($metadata['last_modified']) ? date(DATE_RFC3339, $metadata['last_modified']) : null),
            'mime' => isset($metadata['type']) && $metadata['type'] === 'file' ? $this->disk->mimeType($metadata['path']) : 'directory',
            'isDir' => isset($metadata['type']) && $metadata['type'] === 'dir',
        ];
    }
}
