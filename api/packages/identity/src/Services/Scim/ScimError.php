<?php

namespace Ulams\Identity\Services\Scim;

/**
 * An error in the SCIM error format (RFC 7644 3.12): HTTP status, optional `scimType`, human readable `detail`.
 * Details never carry client-supplied filter text or tokens beyond what the client already sent.
 */
final class ScimError extends \RuntimeException
{
    public const SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:Error';

    public function __construct(public readonly int $status, public readonly ?string $scimType, string $detail, public readonly array $headers = [])
    {
        parent::__construct($detail);
    }

    public static function invalidFilter(string $detail): self
    {
        return new self(400, 'invalidFilter', $detail);
    }

    public static function invalidSyntax(string $detail): self
    {
        return new self(400, 'invalidSyntax', $detail);
    }

    public static function invalidPath(string $detail): self
    {
        return new self(400, 'invalidPath', $detail);
    }

    public static function invalidValue(string $detail): self
    {
        return new self(400, 'invalidValue', $detail);
    }

    public static function noTarget(string $detail): self
    {
        return new self(400, 'noTarget', $detail);
    }

    public static function mutability(string $detail): self
    {
        return new self(400, 'mutability', $detail);
    }

    public static function uniqueness(string $detail): self
    {
        return new self(409, 'uniqueness', $detail);
    }

    public static function tooMany(string $detail): self
    {
        return new self(400, 'tooMany', $detail);
    }

    public static function notFound(string $type, string $id): self
    {
        return new self(404, null, "{$type} {$id} not found.");
    }

    public static function forbidden(string $detail): self
    {
        return new self(403, null, $detail);
    }

    public static function preconditionFailed(): self
    {
        return new self(412, null, 'The resource changed since the version in If-Match; fetch it again.');
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return array_filter([
            'schemas' => [self::SCHEMA],
            'status' => (string) $this->status,
            'scimType' => $this->scimType,
            'detail' => $this->getMessage(),
        ], fn ($v) => $v !== null);
    }
}
