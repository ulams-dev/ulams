<?php

namespace Tests\Helpers;

use EscolaLms\TopicTypes\Helpers\Markdown;

class MarkdownTest extends Markdown
{
    public function verifyParseUrl(array $url): string
    {
        return $this->unparseUrl($url);
    }
}
