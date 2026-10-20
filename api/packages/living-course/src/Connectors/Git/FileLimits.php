<?php

namespace Ulams\LivingCourse\Connectors\Git;

use Ulams\LivingCourse\Connectors\ConnectorException;

/** The limits on the files a Git connector reads (`living_course.connector_limits`), checked on sizes before any content is read. */
final class FileLimits
{
    /**
     * @param array<int,array{path:string,size:?int}> $files
     * @throws ConnectorException
     */
    public static function assert(array $files): void
    {
        $limits = (array) config('living_course.connector_limits');
        if (count($files) > (int) $limits['files']) {
            throw new ConnectorException(sprintf('%d files match, more than the limit of %d. Narrow the paths (for example docs/**/*.md).', count($files), (int) $limits['files']));
        }
        $total = 0;
        foreach ($files as $f) {
            if ($f['size'] !== null && $f['size'] > (int) $limits['file_bytes']) {
                throw new ConnectorException(sprintf('%s is larger than %d KB, the limit for one source file.', $f['path'], (int) $limits['file_bytes'] / 1024));
            }
            $total += (int) $f['size'];
        }
        if ($total > (int) $limits['total_bytes']) {
            throw new ConnectorException(sprintf('The selected files add up to more than %d MB. Narrow the paths.', (int) $limits['total_bytes'] / 1048576));
        }
    }
}
