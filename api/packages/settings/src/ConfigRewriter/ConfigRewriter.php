<?php

namespace EscolaLms\Settings\ConfigRewriter;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

class ConfigRewriter
{
    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * The default configuration path.
     */
    protected string $defaultPath;

    /**
     * The config rewriter object.
     */
    protected FileRewriter $rewriter;

    /**
     * Create a new file configuration loader.
     */
    public function __construct(Filesystem $files, string $defaultPath)
    {
        $this->files = $files;
        $this->defaultPath = $defaultPath;
        $this->rewriter = new FileRewriter;
    }

    /**
     * Write an item value in a file.
     */
    public function write(string $item, $value, string $filename, string $fileExtension = '.php'): bool
    {
        $path = $this->getPath($item, $filename, $fileExtension);

        if (!$path) return false;

        $contents = $this->files->get($path);
        $contents = $this->rewriter->toContent($contents, [$item => $value]);

        return !($this->files->put($path, $contents) === false);
    }

    private function getPath(string $item, string $filename, string $ext = '.php'): string
    {
        $file = "{$this->defaultPath}/{$filename}{$ext}";

        if (!$this->files->exists($file) || !$this->hasKey($file, $item)) {
            // Current rewriter code can not be used to add new keys to files
            throw new Exception("Config file '{$filename}' does not exist or doesn't have key '{$item}'");
        }

        return $file;
    }

    private function hasKey(string $path, string $key): bool
    {
        $contents = file_get_contents($path);
        $vars = eval('?>' . $contents);

        return Arr::has($vars, $key);
    }
}
