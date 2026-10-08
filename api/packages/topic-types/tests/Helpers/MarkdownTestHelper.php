<?php

namespace Tests\Helpers;

use Ulams\TopicTypes\Helpers\Markdown;

/**
 * Exposes the protected Markdown::unparseUrl() to tests.
 */
class MarkdownTestHelper extends Markdown
{
    public function verifyParseUrl(array $url): string
    {
        return $this->unparseUrl($url);
    }
}
