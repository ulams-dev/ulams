<?php

namespace EscolaLms\Templates\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class TemplateSectionTypeEnum extends BasicEnum
{
    const SECTION_HTML = 'html';
    const SECTION_TEXT = 'text';
    const SECTION_URL  = 'url';
    const SECTION_MJML = 'mjml';
    const SECTION_FABRIC = 'fabric.js';
}
