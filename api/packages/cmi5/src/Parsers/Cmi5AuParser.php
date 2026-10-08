<?php

namespace EscolaLms\Cmi5\Parsers;

use EscolaLms\Cmi5\Models\Cmi5Au;
use EscolaLms\Cmi5\Parsers\Contracts\Parser;

class Cmi5AuParser extends AbstractParser implements Parser
{
    public static function parse(array $data): Cmi5Au
    {
        $cmi5Au = new Cmi5Au();
        $cmi5Au->title = $data['title']['langstring'] ?? null;
        $cmi5Au->iri = $data['@attributes']['id'] ?? null;
        $cmi5Au->url = $data['url'] ?? null;

        return $cmi5Au;
    }
}
