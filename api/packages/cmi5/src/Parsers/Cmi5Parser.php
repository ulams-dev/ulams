<?php

namespace EscolaLms\Cmi5\Parsers;

use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Cmi5\Parsers\Contracts\Parser;

class Cmi5Parser extends AbstractParser implements Parser
{
    public static function parse(array $data): Cmi5
    {
        $cmi5 = new Cmi5();
        $cmi5->title = $data['title']['langstring'] ?? null;
        $cmi5->iri = $data['@attributes']['id'] ?? null;

        return $cmi5;
    }
}
