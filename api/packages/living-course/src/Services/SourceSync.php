<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Http\UploadedFile;
use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Diff\FragmentChangeSet;
use Ulams\LivingCourse\Models\Revision;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * Entry point of every way a source can change (ADR 0030): a new revision is stored, compared with
 * the synced one and classified. Connectors (Git, URL, plugins) end here too.
 */
final class SourceSync
{
    public function __construct(
        private readonly RevisionService $revisions,
        private readonly ChangeDetection $detection,
    ) {
    }

    /**
     * @return array{revision:Revision,created:bool,unchanged:bool,changes:?FragmentChangeSet} `created` is false when the file is the latest revision already; `unchanged` when nothing in the text changed
     * @throws UploadRejected
     */
    public function upload(Source $source, UploadedFile $file, ?int $userId): array
    {
        $created = $this->revisions->createFromUpload($source, $file, $userId);
        if ($created['unchanged']) {
            return ['revision' => $created['revision'], 'created' => false, 'unchanged' => true, 'changes' => null];
        }
        $processed = $this->detection->process($created['revision'], $userId);

        return ['revision' => $processed['revision'], 'created' => true, 'unchanged' => $processed['revision']->status === 'unchanged', 'changes' => $processed['changes']];
    }
}
