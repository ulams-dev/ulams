<?php

namespace Tests\Helpers;

use Ulams\TopicTypes\Helpers\Markdown;

class MarkdownTest extends Markdown
{
    public function verifyParseUrl(array $url): string
    {
        return $this->unparseUrl($url);
    }
}
