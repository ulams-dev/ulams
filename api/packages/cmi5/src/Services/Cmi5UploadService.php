<?php

namespace Ulams\Cmi5\Services;

use Ulams\Cmi5\Enums\Cmi5Enum;
use Ulams\Cmi5\Models\Cmi5;
use Ulams\Cmi5\Parsers\Cmi5AuParser;
use Ulams\Cmi5\Parsers\Cmi5Parser;
use Ulams\Cmi5\Repositories\Contracts\Cmi5RepositoryContract;
use Ulams\Cmi5\Services\Contracts\Cmi5UploadServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Ulams\Uploads\UploadGuard;
use Ulams\Uploads\Zip\SafeExtractor;
use Ulams\Uploads\Zip\ZipInspector;
use Ulams\Uploads\Zip\ZipLimits;
use ZipArchive;

class Cmi5UploadService implements Cmi5UploadServiceContract
{
    private Cmi5RepositoryContract $cmi5Repository;

    public function __construct(Cmi5RepositoryContract $cmi5Repository)
    {
        $this->cmi5Repository = $cmi5Repository;
    }

    public function upload(UploadedFile $file): Cmi5
    {
        $path = (string) $file->getRealPath();
        $limits = app(UploadGuard::class)->limitsFor('cmi5');
        // reject zip-slip, symlinks and zip bombs before anything is parsed or saved
        app(ZipInspector::class)->inspect($path, $limits);

        $zip = new ZipArchive();
        $zip->open($path);
        try {
            $cmi5 = $this->parse($zip);
        } finally {
            $zip->close();
        }

        try {
            $this->unzip($cmi5, $path, $limits);
        } catch (\Throwable $e) {
            $cmi5->aus()->delete();
            $cmi5->delete();
            throw $e;
        }

        return $cmi5;
    }

    private function parse(ZipArchive $zip): Cmi5
    {
        $data = $this->xmlToArray($zip->getFromName(Cmi5Enum::MANIFEST_FILE));

        $cmi5 = Cmi5Parser::parse($data[Cmi5Enum::CMI5_KEY]);
        $cmi5aus = Cmi5AuParser::parseCollection($data[Cmi5Enum::CMI5_AU_KEY]);

        return $this->save($cmi5, $cmi5aus);
    }

    private function save(Cmi5 $cmi5, Collection $cmi5Aus): Cmi5
    {
        return $this->cmi5Repository->save($cmi5, $cmi5Aus);
    }

    /**
     * Entry by entry under cmi5/<id> on the cmi5 disk (never ZipArchive::extractTo).
     */
    private function unzip(Cmi5 $cmi5, string $zipPath, ZipLimits $limits): void
    {
        app(SafeExtractor::class)->extractToDisk(
            $zipPath,
            Storage::disk(config('ulams_cmi5.disk')),
            'cmi5/' . $cmi5->getKey(),
            $limits,
        );
    }

    private function xmlToArray(string $xml): array
    {
        $xml = simplexml_load_string($xml);
        $json = json_encode($xml);
        return json_decode($json,TRUE);
    }
}
