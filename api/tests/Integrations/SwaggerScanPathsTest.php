<?php

namespace Tests\Integrations;

use Tests\TestCase;

/**
 * A package that carries OpenAPI annotations but is missing from the scan paths silently drops its
 * endpoints and schemas from the generated document, the CLI spec and the SDK.
 */
class SwaggerScanPathsTest extends TestCase
{
    public function test_every_package_with_openapi_annotations_is_scanned(): void
    {
        $scanned = array_map(
            fn (string $path) => realpath($path) ?: $path,
            config('l5-swagger.documentations.default.paths.annotations')
        );

        $missing = [];
        foreach (glob(base_path('packages/*/src'), GLOB_ONLYDIR) as $src) {
            if (in_array(realpath($src), $scanned, true) || !$this->hasAnnotations($src)) {
                continue;
            }
            $missing[] = 'packages/' . basename(dirname($src)) . '/src';
        }

        $this->assertSame([], $missing, 'Add these paths to config/l5-swagger.php (documentations.default.paths.annotations).');
    }

    private function hasAnnotations(string $dir): bool
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (str_contains($source, '@OA\\') || str_contains($source, '#[OA\\')) {
                return true;
            }
        }

        return false;
    }
}
