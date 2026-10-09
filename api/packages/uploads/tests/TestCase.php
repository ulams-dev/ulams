<?php

namespace Ulams\Uploads\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Ulams\Uploads\UlamsUploadsServiceProvider;

class TestCase extends OrchestraTestCase
{
    use ZipFixtures;

    protected function getPackageProviders($app): array
    {
        return [UlamsUploadsServiceProvider::class];
    }

    protected function tearDown(): void
    {
        $this->cleanZipFixtures();
        parent::tearDown();
    }
}
