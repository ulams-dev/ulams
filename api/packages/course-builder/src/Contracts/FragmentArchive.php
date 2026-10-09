<?php

namespace Ulams\CourseBuilder\Contracts;

/**
 * Fragments that left the live table (the source changed) stay readable here, so citations of
 * elements that still point at them keep resolving. The default has no archive.
 */
interface FragmentArchive
{
    /**
     * @return array{id:string,label:string,section:?string,headingPath:array,text:string,pageStart:?int,pageEnd:?int,source:array{id:string,name:string},sessionId:string,revision:int}|null
     */
    public function find(string $fragmentId): ?array;

    /** @return string[] ids of archived fragments of the session's sources (counted as known citations) */
    public function knownIds(string $sessionId): array;
}
