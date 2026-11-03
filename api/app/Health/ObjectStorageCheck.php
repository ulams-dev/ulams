<?php

namespace App\Health;

use Illuminate\Support\Facades\Storage;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/** HeadBucket on the platform bucket; passes when no object store is configured. */
class ObjectStorageCheck extends Check
{
    public function run(): Result
    {
        $bucket = (string) config('filesystems.disks.s3.bucket');
        $result = Result::make()->meta(['bucket' => $bucket]);
        if ($bucket === '') {
            return $result->ok('No object store configured.');
        }

        try {
            Storage::disk('s3')->getClient()->headBucket(['Bucket' => $bucket]);
        } catch (\Throwable $e) {
            return $result->failed('The object store did not answer HeadBucket: ' . $e->getMessage());
        }

        return $result->ok();
    }
}
