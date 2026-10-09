<?php

namespace Peopleaps\Scorm\Manager;

use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Peopleaps\Scorm\Exception\StorageNotFoundException;
use Ulams\Uploads\Zip\SafeExtractor;
use Ulams\Uploads\Zip\ZipLimits;

class ScormDisk
{
    /**
     * Extract zip file into destination directory.
     *
     * @param UploadedFile|string $file zip source
     * @param string $path The path to the destination.
     *
     * @return bool true on success, false on failure.
     */
    function unzipper($file, $target_dir)
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (!is_string($path) || !is_file($path)) {
            return false;
        }

        // ulams: entry by entry through the upload guard's safe extractor (zip-slip,
        // absolute paths, symlinks and zip bombs are rejected).
        app(SafeExtractor::class)->extractToDisk($path, $this->getDisk(), (string) $target_dir, ZipLimits::fromConfig());

        return true;
    }

    /**
     * @param string $file SCORM archive uri on storage.
     * @param callable $fn function run user stuff before unlink
     */
    public function readScormArchive($file, callable $fn)
    {
        try {
            if (Storage::exists($file)) {
                Storage::delete($file);
            }
            Storage::writeStream($file, $this->getArchiveDisk()->readStream($file));
            $path = Storage::path($file);
            call_user_func($fn, $path);
            // Clean local resources
            $this->clean($file);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            throw new StorageNotFoundException('scorm_archive_not_found');
        }
    }

    private function clean($file)
    {
        try {
            Storage::delete($file);
            Storage::deleteDirectory(dirname($file)); // delete temp dir
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    /**
     * @param string $directory
     * @return bool
     */
    public function deleteScorm($uuid)
    {
        $this->deleteScormArchive($uuid); // try to delete archive if exists.
        return $this->deleteScormContent($uuid);
    }

    /**
     * @param string $directory
     * @return bool
     */
    private function deleteScormContent($folderHashedName)
    {
        try {
            return $this->getDisk()->deleteDirectory($folderHashedName);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    /**
     * @param string $directory
     * @return bool
     */
    private function deleteScormArchive($uuid)
    {
        try {
            return $this->getArchiveDisk()->deleteDirectory($uuid);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    /**
     * @return FilesystemAdapter $disk
     */
    private function getDisk()
    {
        if (!config()->has('filesystems.disks.' . config('scorm.disk'))) {
            throw new StorageNotFoundException('scorm_disk_not_define');
        }
        return Storage::disk(config('scorm.disk'));
    }

    /**
     * @return FilesystemAdapter $disk
     */
    private function getArchiveDisk()
    {
        if (!config()->has('filesystems.disks.' . config('scorm.archive'))) {
            throw new StorageNotFoundException('scorm_archive_disk_not_define');
        }
        return Storage::disk(config('scorm.archive'));
    }
}
