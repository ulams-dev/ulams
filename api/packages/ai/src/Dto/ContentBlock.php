<?php

namespace Ulams\Ai\Dto;

/**
 * One block of the user turn. Stable blocks come first and can be marked cacheable; the client
 * places a cache breakpoint on each marked block (at most four per request).
 */
final class ContentBlock
{
    private function __construct(
        public readonly string $type,
        public readonly string $text = '',
        public readonly ?string $data = null,
        public readonly string $mediaType = 'text/plain',
        public readonly bool $cache = false,
    ) {
    }

    public static function text(string $text, bool $cache = false): self
    {
        return new self('text', $text, null, 'text/plain', $cache);
    }

    /** A PDF passed natively as a document block (base64 data). */
    public static function pdf(string $base64, bool $cache = false): self
    {
        return new self('document', '', $base64, 'application/pdf', $cache);
    }

    public function isDocument(): bool
    {
        return $this->type === 'document';
    }

    /** Stable text used for request hashing (documents are hashed, not inlined). */
    public function fingerprint(): string
    {
        return $this->isDocument() ? 'document:' . hash('sha256', (string) $this->data) : $this->text;
    }
}
