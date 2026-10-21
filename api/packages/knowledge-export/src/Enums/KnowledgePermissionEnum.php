<?php

namespace Ulams\KnowledgeExport\Enums;

use Ulams\Core\Enums\BasicEnum;

class KnowledgePermissionEnum extends BasicEnum
{
    /** Start knowledge exports and download the packs. */
    public const KNOWLEDGE_MANAGE = 'knowledge_manage';

    public static function adminPermissions(): array
    {
        return [self::KNOWLEDGE_MANAGE];
    }
}
