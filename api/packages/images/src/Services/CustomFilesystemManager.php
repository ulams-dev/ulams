<?php

namespace Ulams\Images\Services;

use Ulams\Images\Events\FileDeleted;
use Ulams\Images\Events\FileStored;
use Illuminate\Filesystem\FilesystemManager;

class CustomFilesystemManager extends FilesystemManager
{
    public function __construct($app)
    {
        parent::__construct($app);
    }

    public function delete($paths): bool
    {
        $result = parent::delete($paths);

        if ($result) {
            event(new FileDeleted($paths));
        }

        return $result;
    }

    public function put($path, $contents, $options = [])
    {
        $result = parent::put($path, $contents, $options);
        $this->dispatchEventAfterPut($result);

        return $result;
    }

    public function putFile(string $path, $file, $options = [])
    {
        $result = parent::putFile($path, $file, $options);
        $this->dispatchEventAfterPut($result);

        return $result;
    }

    public function putFileAs(string $path, $file, $name, $options = [])
    {
        $result = parent::putFileAs($path, $file, $name, $options);
        $this->dispatchEventAfterPut($result);

        return $result;
    }

    private function dispatchEventAfterPut($result): void
    {
        if ($result) {
            event(new FileStored($result));
        }
    }
}
