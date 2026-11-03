<?php

namespace Ulams\Identity\Services\Saml;

/** What a validated SAML response says about the person who signed in. Nothing else of the message is kept. */
final class SamlAssertion
{
    /**
     * @param array<string,list<string>> $attributes attribute name => values
     */
    public function __construct(
        public readonly string $nameId,
        public readonly ?string $nameIdFormat,
        public readonly ?string $sessionIndex,
        public readonly array $attributes,
        public readonly string $assertionId,
        public readonly int $expiresAt,
        public readonly bool $usedSha1
    ) {
    }

    /** The first value of the first of these attributes that is present and not empty. */
    public function first(string ...$names): ?string
    {
        foreach ($names as $name) {
            foreach ($this->attributes[$name] ?? [] as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
    }
}
