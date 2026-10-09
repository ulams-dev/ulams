<?php

namespace Ulams\Uploads\Http;

/**
 * A package type that sets response headers itself for its files on the content origin (the CSP of
 * an interactive package depends on its manifest). Registered per prefix in
 * `ulams_uploads.content_headers`; {@see ContentFileController}.
 */
interface ContentHeaderProvider
{
    /**
     * @param string $path normalised path below the content origin, e.g. interactive/<key>/v1/index.html
     * @return array<string, string>|null headers to add, or null when the file must not be served
     */
    public function headersFor(string $path): ?array;
}
