<?php

namespace Ulams\Tenancy\Storage;

use JsonSerializable;
use Stringable;

/**
 * An S3 access key pair. The secret never leaves this object by accident: dumping, casting to a
 * string, encoding as JSON and var_export all show the access key only.
 */
final class StorageCredentials implements JsonSerializable, Stringable
{
    /** Held in a closure: var_export, print_r and var_dump show a Closure, not the secret. */
    private readonly \Closure $reveal;

    public function __construct(
        public readonly string $accessKey,
        #[\SensitiveParameter]
        string $secretKey,
    ) {
        $this->reveal = static fn (): string => $secretKey;
    }

    /** The one place the secret is read: when it is written to the encrypted tenant record or an env file. */
    public function secret(): string
    {
        return ($this->reveal)();
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['accessKey' => $this->accessKey, 'secretKey' => '[redacted]'];
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    public function __toString(): string
    {
        return "storage credentials for {$this->accessKey}";
    }

    /** @return array<string, string> */
    public function __serialize(): array
    {
        return $this->__debugInfo();
    }
}
