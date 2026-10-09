<?php

namespace Ulams\LivingCourse\Connectors;

use InvalidArgumentException;

/**
 * The connectors installed on this instance. `config('living_course.connectors')` lists the keys
 * enabled per installation (default `upload,git,url`); plugin packages register theirs from their
 * service provider (Phase 7.4).
 */
final class SourceConnectorRegistry
{
    /** @var array<string,SourceConnector> */
    private array $connectors = [];

    public function register(SourceConnector $connector): void
    {
        $this->connectors[$connector->key()] = $connector;
    }

    public function has(string $key): bool
    {
        return isset($this->connectors[$key]);
    }

    public function get(string $key): SourceConnector
    {
        return $this->connectors[$key] ?? throw new InvalidArgumentException("Unknown source connector {$key}.");
    }

    /** @return array<string,SourceConnector> the connectors this installation enables */
    public function enabled(): array
    {
        $keys = (array) config('living_course.connectors', ['upload']);

        return array_filter($this->connectors, fn (SourceConnector $c, string $key) => in_array($key, $keys, true), ARRAY_FILTER_USE_BOTH);
    }

    public function isEnabled(string $key): bool
    {
        return isset($this->enabled()[$key]);
    }
}
