<?php

namespace Ulams\Tenancy\Tests\Integration;

use Aws\Iam\IamClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Client as Http;
use Illuminate\Support\Facades\File;
use Mockery;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\Contracts\StorageIdentityProvisionerContract;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\S3BucketProvisioner;
use Ulams\Tenancy\Services\SeaweedFsIdentityProvisioner;
use Ulams\Tenancy\Tests\Mocks\FakeTenantCommandRunner;
use Ulams\Tenancy\Tests\TestCase;

/**
 * Two tenants provisioned by the real commands against a real SeaweedFS (ADR 0041): the keys that land in each
 * tenant's env file work on that tenant's bucket and are refused on the other's, the public-read policy still
 * shows files to anyone, rotation and pruning change which keys work, and deleting a tenant removes its
 * identity. Opt-in: skipped unless S3_COMPAT=1 (`deploy/scripts/s3-compat.sh --store seaweedfs` sets up the
 * store and the variables; the `storage` job of .github/workflows/storage-compat.yml runs it). The database,
 * the env file registry and the artisan child processes are doubles; the object store is not.
 */
class StorageIsolationTest extends TestCase
{
    private string $storage;
    private string $endpoint;
    private S3Client $admin;
    private Http $anonymous;
    private string $suffix;
    /** @var array<string, array<string, string>> host => env values of the last write */
    private array $envFiles = [];
    /** @var list<string> */
    private array $slugs = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('S3_COMPAT') !== '1' || strtolower((string) getenv('S3_COMPAT_FLAVOR')) !== 'seaweedfs') {
            $this->markTestSkipped('Set S3_COMPAT=1 with S3_COMPAT_FLAVOR=seaweedfs (see deploy/scripts/s3-compat.sh) to run against a real SeaweedFS.');
        }

        $this->endpoint = rtrim((string) getenv('S3_COMPAT_ENDPOINT'), '/');
        $this->suffix = substr(bin2hex(random_bytes(4)), 0, 6);
        $this->admin = $this->client((string) getenv('S3_COMPAT_KEY'), (string) getenv('S3_COMPAT_SECRET'));
        $this->anonymous = new Http(['http_errors' => false, 'timeout' => 30]);
        $this->storage = sys_get_temp_dir() . '/ulams-tenancy-isolation-' . uniqid();

        config([
            'ulams_tenancy.s3.endpoint' => $this->endpoint,
            'ulams_tenancy.s3.key' => getenv('S3_COMPAT_KEY'),
            'ulams_tenancy.s3.secret' => getenv('S3_COMPAT_SECRET'),
            'ulams_tenancy.s3.public_read_policy' => true,
            'ulams_tenancy.storage_identity.driver' => 'seaweedfs',
        ]);
        $this->app->instance(BucketProvisionerContract::class, S3BucketProvisioner::fromConfig(config('ulams_tenancy.s3')));
        $this->app->instance(StorageIdentityProvisionerContract::class, SeaweedFsIdentityProvisioner::fromConfig(config('ulams_tenancy.s3')));

        $database = Mockery::mock(DatabaseProvisionerContract::class);
        $database->shouldReceive('ensure', 'drop');
        $domains = Mockery::mock(DomainRegistryContract::class);
        $domains->shouldReceive('storagePath')->andReturn($this->storage);
        $domains->shouldReceive('add')->andReturnUsing(function (string $host, array $values) {
            $this->envFiles[$host] = $values;
        });
        $domains->shouldReceive('remove');
        $this->app->instance(DatabaseProvisionerContract::class, $database);
        $this->app->instance(DomainRegistryContract::class, $domains);
        $this->app->instance(TenantCommandRunnerContract::class, new FakeTenantCommandRunner($this->storage));
    }

    protected function tearDown(): void
    {
        if (getenv('S3_COMPAT') === '1') {
            foreach ($this->slugs as $slug) {
                if (Tenant::query()->firstWhere('slug', $slug)) {
                    $this->artisan('ulams:tenant:delete', ['slug' => $slug, '--force' => true]);
                }
                $this->cleanBucket("ulams-{$slug}");
                try {
                    $this->iam()->deleteUser(['UserName' => SeaweedFsIdentityProvisioner::userName($slug)]);
                } catch (\Throwable) {
                }
            }
        }
        File::deleteDirectory($this->storage ?? '/nonexistent');
        parent::tearDown();
    }

    private function client(string $key, string $secret): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => $key, 'secret' => $secret],
        ]);
    }

    private function iam(): IamClient
    {
        return new IamClient([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => $this->endpoint,
            'credentials' => ['key' => (string) getenv('S3_COMPAT_KEY'), 'secret' => (string) getenv('S3_COMPAT_SECRET')],
        ]);
    }

    private function cleanBucket(string $bucket): void
    {
        try {
            $this->admin->deleteMatchingObjects($bucket, '', '/.*/s');
            $this->admin->deleteBucket(['Bucket' => $bucket]);
        } catch (\Throwable) {
        }
    }

    private function createTenant(string $name): string
    {
        $slug = $name . $this->suffix;
        $this->slugs[] = $slug;
        $this->artisan('ulams:tenant:create', ['slug' => $slug, '--users' => 0])->assertExitCode(0);

        return $slug;
    }

    /** The S3 client a tenant's processes use: the keys of its env file. */
    private function tenantClient(string $slug): S3Client
    {
        $env = $this->envFiles["{$slug}.localhost"];

        return $this->client($env['AWS_ACCESS_KEY_ID'], $env['AWS_SECRET_ACCESS_KEY']);
    }

    private function httpStatus(callable $call): int
    {
        try {
            $call();

            return 200;
        } catch (S3Exception $exception) {
            return $exception->getStatusCode() ?: 0;
        }
    }

    public function testATenantsKeysWorkOnItsOwnBucketAndAreRefusedOnTheOthers(): void
    {
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');
        $alphaBucket = "ulams-{$alpha}";
        $betaBucket = "ulams-{$beta}";
        $a = $this->tenantClient($alpha);
        $b = $this->tenantClient($beta);
        $this->assertNotSame($this->envFiles["{$alpha}.localhost"]['AWS_ACCESS_KEY_ID'], $this->envFiles["{$beta}.localhost"]['AWS_ACCESS_KEY_ID']);
        $this->assertNotSame((string) getenv('S3_COMPAT_KEY'), $this->envFiles["{$alpha}.localhost"]['AWS_ACCESS_KEY_ID'], 'a tenant does not get the platform key');

        // own bucket: what the application does
        $a->putObject(['Bucket' => $alphaBucket, 'Key' => 'course/1/logo.png', 'Body' => 'alpha logo', 'ACL' => 'public-read', 'ContentType' => 'image/png']);
        $a->upload($alphaBucket, 'course/1/video.bin', random_bytes(17 * 1024 * 1024), 'public-read');
        $this->assertSame('alpha logo', (string) $a->getObject(['Bucket' => $alphaBucket, 'Key' => 'course/1/logo.png'])['Body']);
        $b->putObject(['Bucket' => $betaBucket, 'Key' => 'file.txt', 'Body' => 'beta file', 'ACL' => 'public-read']);

        // the other tenant's bucket. Reading a file by its exact name is public on purpose (the bucket policy
        // serves uploaded files by URL, to anyone, with or without a key); listing, writing and deleting are not.
        $this->assertSame(200, $this->httpStatus(fn () => $a->getObject(['Bucket' => $betaBucket, 'Key' => 'file.txt'])), 'public files are readable by name');
        $this->assertSame(403, $this->httpStatus(fn () => $a->listObjectsV2(['Bucket' => $betaBucket])), 'list');
        $this->assertSame(403, $this->httpStatus(fn () => $a->putObject(['Bucket' => $betaBucket, 'Key' => 'planted.txt', 'Body' => 'x'])), 'write');
        $this->assertSame(403, $this->httpStatus(fn () => $a->deleteObject(['Bucket' => $betaBucket, 'Key' => 'file.txt'])), 'delete');
        $bulk = $a->deleteObjects(['Bucket' => $betaBucket, 'Delete' => ['Objects' => [['Key' => 'file.txt']]]]);
        $this->assertEmpty($bulk['Deleted'] ?? [], 'bulk delete removes nothing (S3 answers 200 and reports the refusal per key)');
        $this->assertSame('AccessDenied', $bulk['Errors'][0]['Code'] ?? null, 'bulk delete');
        $this->assertSame(403, $this->httpStatus(fn () => $b->listObjectsV2(['Bucket' => $alphaBucket])), 'list the other way');
        $this->assertSame(403, $this->httpStatus(fn () => $b->putObject(['Bucket' => $alphaBucket, 'Key' => 'planted.txt', 'Body' => 'x'])), 'write the other way');
        $this->assertSame(403, $this->httpStatus(fn () => $b->deleteObject(['Bucket' => $alphaBucket, 'Key' => 'course/1/logo.png'])), 'delete the other way');
        // nor the platform bucket, and nobody gets to manage buckets or policies
        $this->assertSame(403, $this->httpStatus(fn () => $a->putBucketPolicy(['Bucket' => $alphaBucket, 'Policy' => '{}'])), 'a tenant cannot change its own bucket policy');
        $this->assertSame(403, $this->httpStatus(fn () => $a->createBucket(['Bucket' => "ulams-rogue{$this->suffix}"])), 'a tenant cannot create buckets');
        $this->assertSame('beta file', (string) $this->admin->getObject(['Bucket' => $betaBucket, 'Key' => 'file.txt'])['Body'], 'the other bucket is untouched');
        $this->assertSame(['file.txt'], array_column($this->admin->listObjectsV2(['Bucket' => $betaBucket])['Contents'], 'Key'));
        $this->assertSame(['course/1/logo.png', 'course/1/video.bin'], array_column($this->admin->listObjectsV2(['Bucket' => $alphaBucket])['Contents'], 'Key'));
    }

    public function testWithoutAPublicReadPolicyAnotherTenantCannotEvenReadAFile(): void
    {
        config(['ulams_tenancy.s3.public_read_policy' => false]);
        $this->app->instance(BucketProvisionerContract::class, S3BucketProvisioner::fromConfig(config('ulams_tenancy.s3')));
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');
        $a = $this->tenantClient($alpha);
        $b = $this->tenantClient($beta);
        $b->putObject(['Bucket' => "ulams-{$beta}", 'Key' => 'private.txt', 'Body' => 'beta only']);

        $this->assertSame(403, $this->httpStatus(fn () => $a->getObject(['Bucket' => "ulams-{$beta}", 'Key' => 'private.txt'])), 'read');
        $this->assertSame(403, $this->httpStatus(fn () => $a->headObject(['Bucket' => "ulams-{$beta}", 'Key' => 'private.txt'])), 'head');
        $this->assertSame(403, $this->anonymous->get("{$this->endpoint}/ulams-{$beta}/private.txt")->getStatusCode(), 'anonymous');
        $this->assertSame('beta only', (string) $b->getObject(['Bucket' => "ulams-{$beta}", 'Key' => 'private.txt'])['Body'], 'the owner still reads it');
    }

    public function testPublicReadStillShowsFilesToAnyone(): void
    {
        $alpha = $this->createTenant('alpha');
        $this->tenantClient($alpha)->putObject(['Bucket' => "ulams-{$alpha}", 'Key' => 'logo.png', 'Body' => 'png', 'ACL' => 'public-read']);

        $this->assertSame(200, $this->anonymous->get("{$this->endpoint}/ulams-{$alpha}/logo.png")->getStatusCode());
        $this->assertContains($this->anonymous->get("{$this->endpoint}/ulams-{$alpha}?list-type=2")->getStatusCode(), [401, 403]);
        $this->assertContains($this->anonymous->put("{$this->endpoint}/ulams-{$alpha}/evil.txt", ['body' => 'x'])->getStatusCode(), [401, 403]);
    }

    public function testRotationAndPruningChangeWhichKeysWork(): void
    {
        $alpha = $this->createTenant('alpha');
        $bucket = "ulams-{$alpha}";
        $old = $this->tenantClient($alpha);
        $old->putObject(['Bucket' => $bucket, 'Key' => 'f.txt', 'Body' => 'x']);
        $oldKey = $this->envFiles["{$alpha}.localhost"]['AWS_ACCESS_KEY_ID'];

        $this->artisan('ulams:tenant:sync-env', ['--rotate-storage-keys' => true, '--tenant' => [$alpha]])->assertExitCode(0);
        $newKey = $this->envFiles["{$alpha}.localhost"]['AWS_ACCESS_KEY_ID'];
        $this->assertNotSame($oldKey, $newKey);
        $fresh = $this->tenantClient($alpha);
        $this->assertSame('x', (string) $fresh->getObject(['Bucket' => $bucket, 'Key' => 'f.txt'])['Body']);
        $this->assertSame('x', (string) $old->getObject(['Bucket' => $bucket, 'Key' => 'f.txt'])['Body'], 'the old key works until pruned');

        $this->artisan('ulams:tenant:sync-env', ['--prune-storage-keys' => true, '--tenant' => [$alpha]])->assertExitCode(0);
        $this->assertContains($this->httpStatus(fn () => $old->getObject(['Bucket' => $bucket, 'Key' => 'f.txt'])), [401, 403], 'the pruned key is refused');
        $this->assertSame('x', (string) $fresh->getObject(['Bucket' => $bucket, 'Key' => 'f.txt'])['Body']);
    }

    public function testDeletingATenantRemovesItsIdentityAndKeys(): void
    {
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');
        $alphaClient = $this->tenantClient($alpha);
        $names = fn () => array_column($this->iam()->listUsers()['Users'], 'UserName');
        $this->assertContains(SeaweedFsIdentityProvisioner::userName($alpha), $names());

        $this->artisan('ulams:tenant:delete', ['slug' => $alpha, '--force' => true])->assertExitCode(0);

        $this->assertNotContains(SeaweedFsIdentityProvisioner::userName($alpha), $names());
        $this->assertContains(SeaweedFsIdentityProvisioner::userName($beta), $names(), 'the other tenant keeps its identity');
        $this->assertContains($this->httpStatus(fn () => $alphaClient->listObjectsV2(['Bucket' => "ulams-{$beta}"])), [401, 403]);
        $this->assertSame(404, $this->httpStatus(fn () => $this->admin->headBucket(['Bucket' => "ulams-{$alpha}"])), 'the bucket went with it');
    }

    public function testTheKeysNeverAppearInTheCommandOutput(): void
    {
        $slug = 'alpha' . $this->suffix;
        $this->slugs[] = $slug;
        $this->artisan('ulams:tenant:create', ['slug' => $slug, '--users' => 0])->assertExitCode(0);
        $env = $this->envFiles["{$slug}.localhost"];

        ob_start();
        \Illuminate\Support\Facades\Artisan::call('ulams:tenant:sync-env', ['--rotate-storage-keys' => true]);
        $output = ob_get_clean() . \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringNotContainsString($env['AWS_SECRET_ACCESS_KEY'], $output);
        $this->assertStringNotContainsString($this->envFiles["{$slug}.localhost"]['AWS_SECRET_ACCESS_KEY'], $output);
        $this->assertStringNotContainsString((string) getenv('S3_COMPAT_SECRET'), $output);
    }
}
