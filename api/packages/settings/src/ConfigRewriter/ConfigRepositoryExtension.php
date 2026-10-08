<?php

namespace EscolaLms\Settings\ConfigRewriter;

use Exception;
use Illuminate\Config\Repository as RepositoryBase;

class ConfigRepositoryExtension extends RepositoryBase
{
    /**
     * The config rewriter extension object.
     */
    protected ConfigRewriter $writer;

    /**
     * Create a new configuration repository.
     */
    public function __construct(ConfigRewriter $writer, array $items = [])
    {
        parent::__construct($items);
        $this->writer = $writer;
    }

    /**
     * Write a given configuration value to file.
     */
    public function write(string $key, $value): bool
    {
        list($filename, $item) = $this->parseKey($key);
        $result = $this->writer->write($item, $value, $filename);

        if (!$result) throw new Exception('File could not be written to');

        $this->set($key, $value);

        return $result;
    }

    /**
     * Split key into 2 parts. The first part will be the filename
     */
    private function parseKey(string $key): array
    {
        return preg_split('/\./', $key, 2);
    }
}
