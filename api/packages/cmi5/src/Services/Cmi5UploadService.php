<?php

namespace EscolaLms\Cmi5\Services;

use EscolaLms\Cmi5\Enums\Cmi5Enum;
use EscolaLms\Cmi5\Models\Cmi5;
use EscolaLms\Cmi5\Parsers\Cmi5AuParser;
use EscolaLms\Cmi5\Parsers\Cmi5Parser;
use EscolaLms\Cmi5\Repositories\Contracts\Cmi5RepositoryContract;
use EscolaLms\Cmi5\Services\Contracts\Cmi5UploadServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
        $zip = new ZipArchive();
        $zip->open($file);

        $cmi5 = $this->parse($zip);
        $this->unzip($cmi5, $zip);

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

    private function unzip(Cmi5 $cmi5, ZipArchive $zip): void
    {
        $rootDir = 'cmi5';
        $disk = Storage::disk(config('escolalms_cmi5.disk'));
        $destinationDir = $disk->path($rootDir . DIRECTORY_SEPARATOR . $cmi5->getKey());

        if (!$disk->exists($destinationDir)) {
            $disk->makeDirectory($destinationDir);
        }

        $zip->extractTo($destinationDir);
        $zip->close();
    }

    private function xmlToArray(string $xml): array
    {
        $xml = simplexml_load_string($xml);
        $json = json_encode($xml);
        return json_decode($json,TRUE);
    }
}
