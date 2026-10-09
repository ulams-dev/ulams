<?php

namespace Ulams\Uploads\Tests;

use ZipArchive;

/**
 * Builds hostile and benign zip fixtures at test time (no binary fixtures in the repository).
 * Other packages' tests use it too (SCORM, cmi5, course import).
 */
trait ZipFixtures
{
    /** @var string[] */
    private array $zipFixtureFiles = [];

    /**
     * @param array<string, string> $entries name => contents (a name ending in "/" is a directory)
     * @param array<string, string> $symlinks name => link target
     */
    protected function makeZip(array $entries, array $symlinks = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ulams-zip-') . '.zip';
        $this->zipFixtureFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            if (str_ends_with($name, '/')) {
                $zip->addEmptyDir(rtrim($name, '/'));
                continue;
            }
            $zip->addFromString($name, $contents);
        }
        foreach ($symlinks as $name => $target) {
            $zip->addFromString($name, $target);
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0120777 << 16));
        }
        $zip->close();

        return $path;
    }

    /**
     * A valid SCORM 1.2 package with one SCO, optionally with extra (hostile) entries.
     *
     * @param array<string, string> $extra
     */
    protected function makeScormZip(array $extra = [], array $symlinks = []): string
    {
        $manifest = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="ulams.fixture" version="1"
  xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
  xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
  <organizations default="org">
    <organization identifier="org">
      <title>Fixture</title>
      <item identifier="item_1" identifierref="res_1"><title>Fixture SCO</title></item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="res_1" type="webcontent" adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
    </resource>
  </resources>
</manifest>
XML;

        return $this->makeZip(array_merge([
            'imsmanifest.xml' => $manifest,
            'index.html' => '<!doctype html><title>Fixture</title><p>SCO</p>',
        ], $extra), $symlinks);
    }

    /** A zip whose single entry inflates to `$megabytes` of zeros (ratio about 1000:1). */
    protected function makeZipBomb(int $megabytes = 8, string $prefixManifest = ''): string
    {
        $entries = ['bomb.bin' => str_repeat("\0", $megabytes * 1024 * 1024)];
        if ($prefixManifest !== '') {
            $entries = ['imsmanifest.xml' => $prefixManifest] + $entries;
        }

        return $this->makeZip($entries);
    }

    protected function cleanZipFixtures(): void
    {
        foreach ($this->zipFixtureFiles as $file) {
            @unlink($file);
            @unlink(substr($file, 0, -4));
        }
        $this->zipFixtureFiles = [];
    }
}
