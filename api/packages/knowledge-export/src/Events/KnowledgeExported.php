<?php

namespace Ulams\KnowledgeExport\Events;

use Ulams\KnowledgeExport\Models\KnowledgeExport;

/** A pack was written (not fired when an automatic re-export found the content unchanged). */
final class KnowledgeExported
{
    public function __construct(public readonly KnowledgeExport $export)
    {
    }
}
