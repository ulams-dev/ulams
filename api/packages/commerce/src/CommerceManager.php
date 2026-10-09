<?php

namespace Ulams\Commerce;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Ulams\Commerce\Contracts\CommerceProvider;

/** Resolves the configured commerce provider (`commerce.provider`); adapters register with `extend`. */
final class CommerceManager
{
    /** @var array<string,Closure(Container):CommerceProvider> */
    private array $factories = [];

    /** @var array<string,CommerceProvider> */
    private array $resolved = [];

    public function __construct(private readonly Container $container)
    {
    }

    /** @param Closure(Container):CommerceProvider $factory */
    public function extend(string $key, Closure $factory): void
    {
        $this->factories[$key] = $factory;
        unset($this->resolved[$key]);
    }

    public function provider(?string $key = null): CommerceProvider
    {
        $key ??= (string) config('commerce.provider', 'wellms');
        if (!isset($this->factories[$key])) {
            throw new InvalidArgumentException("Unknown commerce provider \"{$key}\".");
        }

        return $this->resolved[$key] ??= ($this->factories[$key])($this->container);
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->factories);
    }
}
