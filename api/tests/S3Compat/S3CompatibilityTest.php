<?php

namespace Tests\S3Compat;

use Aws\Iam\IamClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use GuzzleHttp\Client as Http;
use PHPUnit\Framework\TestCase;

/**
 * The S3 operations ulams uses, run against a real object store (docs/plans/phase-8-self-hosting.md 4.3,
 * ADR 0041). Opt-in: skipped unless S3_COMPAT=1, so the default pipeline never needs a store.
 *
 *   S3_COMPAT=1 S3_COMPAT_ENDPOINT=http://storage:8333 S3_COMPAT_KEY=... S3_COMPAT_SECRET=... \
 *   S3_COMPAT_FLAVOR=seaweedfs ./vendor/bin/phpunit tests/S3Compat
 *
 * `deploy/scripts/s3-compat.sh` starts the store in a throwaway container and runs this file.
 * S3_COMPAT_FLAVOR is `seaweedfs` (identity checks use the embedded IAM API) or anything else
 * (`minio`: the baseline for everything except identities).
 *
 * The operations, from the inventory in the plan: PutObject with `x-amz-acl: public-read`,
 * Content-Type and Content-Disposition; multipart over 16 MB; GetObject and streamed reads;
 * HeadObject; DeleteObject and DeleteObjects; CopyObject; ListObjectsV2 with prefix; PutObjectAcl;
 * HeadBucket, CreateBucket, PutBucketPolicy, DeleteBucket. On top of the inventory: presigned URLs
 * and CORS (not used today, recorded for operators who put a CDN or a browser upload in front),
 * a public-read policy seen anonymously and denial for a limited identity.
 */
class S3CompatibilityTest extends TestCase
{
    private S3Client $s3;
    private Http $anonymous;
    private string $endpoint;
    private string $flavor;
    private string $suffix;
    /** @var list<string> */
    private array $buckets = [];
    /** @var list<string> */
    private array $users = [];

    protected function setUp(): void
    {
        if (getenv('S3_COMPAT') !== '1') {
            $this->markTestSkipped('Set S3_COMPAT=1 (and S3_COMPAT_ENDPOINT, S3_COMPAT_KEY, S3_COMPAT_SECRET) to run against a real object store.');
        }

        $this->endpoint = rtrim((string) getenv('S3_COMPAT_ENDPOINT'), '/');
        $this->flavor = strtolower((string) (getenv('S3_COMPAT_FLAVOR') ?: 'seaweedfs'));
        $this->suffix = bin2hex(random_bytes(4));
        $this->s3 = $this->client((string) getenv('S3_COMPAT_KEY'), (string) getenv('S3_COMPAT_SECRET'));
        $this->anonymous = new Http(['http_errors' => false, 'timeout' => 30]);
    }

    protected function tearDown(): void
    {
        if (getenv('S3_COMPAT') !== '1') {
            return;
        }
        foreach ($this->buckets as $bucket) {
            try {
                $this->s3->deleteMatchingObjects($bucket, '', '/.*/s');
                $this->s3->deleteBucket(['Bucket' => $bucket]);
            } catch (\Throwable) {
            }
        }
        if ($this->flavor === 'seaweedfs') {
            foreach ($this->users as $user) {
                try {
                    foreach ($this->iam()->listAccessKeys(['UserName' => $user])['AccessKeyMetadata'] as $key) {
                        $this->iam()->deleteAccessKey(['UserName' => $user, 'AccessKeyId' => $key['AccessKeyId']]);
                    }
                    $this->iam()->deleteUser(['UserName' => $user]);
                } catch (\Throwable) {
                }
            }
        }
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

    private function bucket(string $name = 'a', bool $publicRead = false): string
    {
        $bucket = "compat-{$name}-{$this->suffix}";
        $this->s3->createBucket(['Bucket' => $bucket]);
        $this->buckets[] = $bucket;
        if ($publicRead) {
            $this->s3->putBucketPolicy(['Bucket' => $bucket, 'Policy' => json_encode([
                'Version' => '2012-10-17',
                'Statement' => [[
                    'Effect' => 'Allow',
                    'Principal' => ['AWS' => ['*']],
                    'Action' => ['s3:GetObject'],
                    'Resource' => ["arn:aws:s3:::{$bucket}/*"],
                ]],
            ])]);
        }

        return $bucket;
    }

    private function url(string $bucket, string $key = ''): string
    {
        return "{$this->endpoint}/{$bucket}/" . ltrim($key, '/');
    }

    private function statusOf(callable $call): int
    {
        try {
            $call();

            return 200;
        } catch (S3Exception $exception) {
            return $exception->getStatusCode() ?: 0;
        }
    }

    /** @return array{0: S3Client, 1: string, 2: string} a limited identity for one bucket */
    private function identity(string $bucket): array
    {
        $this->requireSeaweedFs();
        $user = "compat-{$bucket}";
        $this->users[] = $user;
        $this->iam()->createUser(['UserName' => $user]);
        $key = $this->iam()->createAccessKey(['UserName' => $user])['AccessKey'];
        $this->iam()->putUserPolicy([
            'UserName' => $user,
            'PolicyName' => 'bucket',
            'PolicyDocument' => json_encode(['Version' => '2012-10-17', 'Statement' => [[
                'Effect' => 'Allow',
                'Action' => ['s3:*'],
                'Resource' => ["arn:aws:s3:::{$bucket}", "arn:aws:s3:::{$bucket}/*"],
            ]]]),
        ]);

        return [$this->client($key['AccessKeyId'], $key['SecretAccessKey']), $key['AccessKeyId'], $key['SecretAccessKey']];
    }

    private function requireSeaweedFs(): void
    {
        if ($this->flavor !== 'seaweedfs') {
            $this->markTestSkipped('Identities are provisioned through the SeaweedFS embedded IAM API; the other stores are compared on the S3 operations only.');
        }
    }

    public function testBucketLifecycle(): void
    {
        $bucket = $this->bucket();
        $this->s3->headBucket(['Bucket' => $bucket]);
        $this->assertContains($bucket, array_column($this->s3->listBuckets()['Buckets'], 'Name'));

        $this->s3->deleteBucket(['Bucket' => $bucket]);
        $this->assertSame(404, $this->statusOf(fn () => $this->s3->headBucket(['Bucket' => $bucket])));
    }

    public function testPutWithPublicReadAclContentTypeAndDisposition(): void
    {
        $bucket = $this->bucket();
        $this->s3->putObject([
            'Bucket' => $bucket,
            'Key' => 'course/1/file.pdf',
            'Body' => 'pdf-bytes',
            'ACL' => 'public-read',
            'ContentType' => 'application/pdf',
            'ContentDisposition' => 'attachment; filename="file.pdf"',
        ]);

        $head = $this->s3->headObject(['Bucket' => $bucket, 'Key' => 'course/1/file.pdf']);
        $this->assertSame('application/pdf', $head['ContentType']);
        $this->assertSame('attachment; filename="file.pdf"', $head['ContentDisposition']);
        $this->assertSame(9, (int) $head['ContentLength']);
        $this->assertSame('pdf-bytes', (string) $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'course/1/file.pdf'])['Body']);
    }

    public function testStreamedRead(): void
    {
        $bucket = $this->bucket();
        $payload = random_bytes(300_000);
        $this->s3->putObject(['Bucket' => $bucket, 'Key' => 'blob.bin', 'Body' => $payload]);

        $stream = $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'blob.bin'])['Body'];
        $read = '';
        while (!$stream->eof()) {
            $read .= $stream->read(8192);
        }
        $this->assertSame(md5($payload), md5($read));

        $range = $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'blob.bin', 'Range' => 'bytes=10-19'])['Body'];
        $this->assertSame(substr($payload, 10, 10), (string) $range);
    }

    public function testDeleteObjectAndDeleteObjects(): void
    {
        $bucket = $this->bucket();
        foreach (['a.txt', 'b.txt', 'c.txt'] as $key) {
            $this->s3->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => $key]);
        }

        $this->s3->deleteObject(['Bucket' => $bucket, 'Key' => 'a.txt']);
        $this->assertSame(404, $this->statusOf(fn () => $this->s3->headObject(['Bucket' => $bucket, 'Key' => 'a.txt'])));

        $result = $this->s3->deleteObjects(['Bucket' => $bucket, 'Delete' => ['Objects' => [['Key' => 'b.txt'], ['Key' => 'c.txt']]]]);
        $this->assertCount(2, $result['Deleted']);
        $this->assertEmpty($this->s3->listObjectsV2(['Bucket' => $bucket])['Contents'] ?? []);
    }

    public function testCopyAndMove(): void
    {
        $bucket = $this->bucket();
        $this->s3->putObject(['Bucket' => $bucket, 'Key' => 'from/a.txt', 'Body' => 'copy me', 'ContentType' => 'text/plain']);

        $this->s3->copyObject(['Bucket' => $bucket, 'Key' => 'to/a.txt', 'CopySource' => "{$bucket}/from/a.txt", 'ACL' => 'public-read']);

        $this->assertSame('copy me', (string) $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'to/a.txt'])['Body']);
        $this->s3->deleteObject(['Bucket' => $bucket, 'Key' => 'from/a.txt']);
        $this->assertSame(404, $this->statusOf(fn () => $this->s3->headObject(['Bucket' => $bucket, 'Key' => 'from/a.txt'])));
    }

    public function testListWithPrefixDelimiterAndPagination(): void
    {
        $bucket = $this->bucket();
        foreach (['course/1/a.txt', 'course/1/b.txt', 'course/2/c.txt', 'other/d.txt'] as $key) {
            $this->s3->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => 'x']);
        }

        $list = $this->s3->listObjectsV2(['Bucket' => $bucket, 'Prefix' => 'course/1/']);
        $this->assertSame(['course/1/a.txt', 'course/1/b.txt'], array_column($list['Contents'], 'Key'));

        $dirs = $this->s3->listObjectsV2(['Bucket' => $bucket, 'Prefix' => 'course/', 'Delimiter' => '/']);
        $this->assertSame(['course/1/', 'course/2/'], array_column($dirs['CommonPrefixes'], 'Prefix'));

        $first = $this->s3->listObjectsV2(['Bucket' => $bucket, 'MaxKeys' => 2]);
        $this->assertTrue($first['IsTruncated']);
        $second = $this->s3->listObjectsV2(['Bucket' => $bucket, 'MaxKeys' => 2, 'ContinuationToken' => $first['NextContinuationToken']]);
        $this->assertSame(4, count($first['Contents']) + count($second['Contents']));
    }

    public function testMultipartUploadOver16MbKeepsTheContent(): void
    {
        $bucket = $this->bucket();
        $file = tempnam(sys_get_temp_dir(), 's3c');
        $handle = fopen($file, 'wb');
        for ($i = 0; $i < 20; $i++) {
            fwrite($handle, random_bytes(1024 * 1024));
        }
        fclose($handle);

        try {
            $result = (new MultipartUploader($this->s3, $file, ['bucket' => $bucket, 'key' => 'big/video.bin', 'part_size' => 6 * 1024 * 1024, 'acl' => 'public-read']))->upload();
            $this->assertArrayHasKey('ObjectURL', $result->toArray());

            $head = $this->s3->headObject(['Bucket' => $bucket, 'Key' => 'big/video.bin']);
            $this->assertSame(20 * 1024 * 1024, (int) $head['ContentLength']);
            $downloaded = tempnam(sys_get_temp_dir(), 's3d');
            $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'big/video.bin', 'SaveAs' => $downloaded]);
            $this->assertSame(md5_file($file), md5_file($downloaded));
            @unlink($downloaded);

            // an aborted upload leaves nothing behind
            $upload = $this->s3->createMultipartUpload(['Bucket' => $bucket, 'Key' => 'aborted.bin']);
            $this->s3->uploadPart(['Bucket' => $bucket, 'Key' => 'aborted.bin', 'UploadId' => $upload['UploadId'], 'PartNumber' => 1, 'Body' => str_repeat('a', 5 * 1024 * 1024)]);
            $this->s3->abortMultipartUpload(['Bucket' => $bucket, 'Key' => 'aborted.bin', 'UploadId' => $upload['UploadId']]);
            $this->assertSame(404, $this->statusOf(fn () => $this->s3->headObject(['Bucket' => $bucket, 'Key' => 'aborted.bin'])));
        } finally {
            @unlink($file);
        }
    }

    public function testSdkUploadHelperUsedByTheApplication(): void
    {
        $bucket = $this->bucket();
        $payload = random_bytes(17 * 1024 * 1024);
        $this->s3->upload($bucket, 'upload/helper.bin', $payload, 'public-read');

        $this->assertSame(md5($payload), md5((string) $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'upload/helper.bin'])['Body']));
    }

    public function testPutObjectAclAfterUpload(): void
    {
        if ($this->flavor === 'minio') {
            $this->markTestSkipped('MinIO answers 501 NotImplemented to PutObjectAcl (the baseline already needs the caller to cope).');
        }
        $bucket = $this->bucket();
        $this->s3->putObject(['Bucket' => $bucket, 'Key' => 'seg.ts', 'Body' => 'ts']);

        $this->s3->putObjectAcl(['Bucket' => $bucket, 'Key' => 'seg.ts', 'ACL' => 'public-read']);

        $this->assertSame('ts', (string) $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'seg.ts'])['Body']);
    }

    /**
     * Known issue, recorded in ADR 0041: SeaweedFS 4.37 answers 500 to PutObjectAcl on a key with a "/"
     * (it looks the entry up without its directory). Access does not depend on it, the bucket policy
     * makes objects public; `ProcessVideo` tolerates the failure. When this passes on a newer tag the
     * workaround can go.
     */
    public function testPutObjectAclOnANestedKey(): void
    {
        if ($this->flavor === 'minio') {
            $this->markTestSkipped('MinIO answers 501 NotImplemented to PutObjectAcl.');
        }
        $bucket = $this->bucket();
        $this->s3->putObject(['Bucket' => $bucket, 'Key' => 'video/seg.ts', 'Body' => 'ts']);

        try {
            $this->s3->putObjectAcl(['Bucket' => $bucket, 'Key' => 'video/seg.ts', 'ACL' => 'public-read']);
        } catch (S3Exception $exception) {
            if ($this->flavor === 'seaweedfs' && $exception->getStatusCode() === 500) {
                $this->markTestSkipped('Known SeaweedFS issue: PutObjectAcl on a nested key answers 500 (ADR 0041).');
            }
            throw $exception;
        }
        $this->assertSame('ts', (string) $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'video/seg.ts'])['Body']);
    }

    public function testPresignedGetAndPut(): void
    {
        $bucket = $this->bucket();
        $this->s3->putObject(['Bucket' => $bucket, 'Key' => 'private/report.txt', 'Body' => 'secret report']);

        $this->assertSame(403, $this->anonymous->get($this->url($bucket, 'private/report.txt'))->getStatusCode(), 'private object must not be anonymous');

        $get = (string) $this->s3->createPresignedRequest($this->s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => 'private/report.txt']), '+5 minutes')->getUri();
        $response = $this->anonymous->get($get);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('secret report', (string) $response->getBody());

        $put = (string) $this->s3->createPresignedRequest($this->s3->getCommand('PutObject', ['Bucket' => $bucket, 'Key' => 'uploaded.txt']), '+5 minutes')->getUri();
        $this->assertSame(200, $this->anonymous->put($put, ['body' => 'via presigned put'])->getStatusCode());
        $this->assertSame('via presigned put', (string) $this->s3->getObject(['Bucket' => $bucket, 'Key' => 'uploaded.txt'])['Body']);

        $expired = (string) $this->s3->createPresignedRequest($this->s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => 'private/report.txt']), '+1 second')->getUri();
        sleep(3);
        $this->assertSame(403, $this->anonymous->get($expired)->getStatusCode(), 'an expired URL must be refused');
    }

    public function testPublicReadPolicyIsSeenAnonymously(): void
    {
        $bucket = $this->bucket('public', publicRead: true);
        $this->s3->putObject(['Bucket' => $bucket, 'Key' => 'logo.svg', 'Body' => '<svg/>', 'ContentType' => 'image/svg+xml', 'ACL' => 'public-read']);

        $response = $this->anonymous->get($this->url($bucket, 'logo.svg'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<svg/>', (string) $response->getBody());
        $this->assertStringStartsWith('image/svg+xml', $response->getHeaderLine('Content-Type'));

        $this->assertContains($this->anonymous->put($this->url($bucket, 'evil.txt'), ['body' => 'x'])->getStatusCode(), [401, 403], 'anonymous write must be refused');
        $this->assertContains($this->anonymous->get("{$this->endpoint}/{$bucket}?list-type=2")->getStatusCode(), [401, 403], 'anonymous listing must be refused');
        $this->assertContains($this->anonymous->delete($this->url($bucket, 'logo.svg'))->getStatusCode(), [401, 403], 'anonymous delete must be refused');
    }

    public function testPrivateBucketIsNotAnonymous(): void
    {
        $bucket = $this->bucket('private');
        $this->s3->putObject(['Bucket' => $bucket, 'Key' => 'x.txt', 'Body' => 'x']);

        $this->assertContains($this->anonymous->get($this->url($bucket, 'x.txt'))->getStatusCode(), [401, 403]);
    }

    public function testBucketPolicyCanBeReplacedAndRead(): void
    {
        $bucket = $this->bucket('policy', publicRead: true);
        $this->s3->putBucketPolicy(['Bucket' => $bucket, 'Policy' => json_encode(['Version' => '2012-10-17', 'Statement' => [[
            'Effect' => 'Allow', 'Principal' => ['AWS' => ['*']], 'Action' => ['s3:GetObject'], 'Resource' => ["arn:aws:s3:::{$bucket}/*"],
        ]]])]);

        $policy = json_decode((string) $this->s3->getBucketPolicy(['Bucket' => $bucket])['Policy'], true);
        $this->assertSame('Allow', $policy['Statement'][0]['Effect']);
    }

    public function testCorsRulesCanBeSetAndRead(): void
    {
        if ($this->flavor === 'minio') {
            $this->markTestSkipped('MinIO answers 501 NotImplemented to PutBucketCors (CORS is server wide there).');
        }
        $bucket = $this->bucket('cors');
        $this->s3->putBucketCors(['Bucket' => $bucket, 'CORSConfiguration' => ['CORSRules' => [[
            'AllowedOrigins' => ['https://academy.example'],
            'AllowedMethods' => ['GET', 'HEAD'],
            'AllowedHeaders' => ['*'],
            'MaxAgeSeconds' => 3600,
        ]]]]);

        $rules = $this->s3->getBucketCors(['Bucket' => $bucket])['CORSRules'];
        $this->assertSame(['https://academy.example'], $rules[0]['AllowedOrigins']);

        $this->s3->putObject(['Bucket' => $bucket, 'Key' => 'f.txt', 'Body' => 'x', 'ACL' => 'public-read']);
        $preflight = $this->anonymous->request('OPTIONS', $this->url($bucket, 'f.txt'), ['headers' => [
            'Origin' => 'https://academy.example',
            'Access-Control-Request-Method' => 'GET',
        ]]);
        $this->assertSame('https://academy.example', $preflight->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testObjectKeysWithUnicodeAndSpacesRoundTrip(): void
    {
        $bucket = $this->bucket();
        $key = 'course/7/zażółć gęślą jaźń (1).txt';
        $this->s3->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => 'ok']);

        $this->assertSame('ok', (string) $this->s3->getObject(['Bucket' => $bucket, 'Key' => $key])['Body']);
        $this->assertSame([$key], array_column($this->s3->listObjectsV2(['Bucket' => $bucket, 'Prefix' => 'course/7/'])['Contents'], 'Key'));
    }

    public function testDeleteMatchingObjectsEmptiesABucketWithManyObjects(): void
    {
        $bucket = $this->bucket();
        for ($i = 0; $i < 25; $i++) {
            $this->s3->putObject(['Bucket' => $bucket, 'Key' => "bulk/{$i}.txt", 'Body' => (string) $i]);
        }

        $this->s3->deleteMatchingObjects($bucket, '', '/.*/s');
        $this->s3->deleteBucket(['Bucket' => $bucket]);

        $this->assertSame(404, $this->statusOf(fn () => $this->s3->headBucket(['Bucket' => $bucket])));
    }

    public function testALimitedIdentityWorksInItsOwnBucket(): void
    {
        $bucket = $this->bucket('own');
        [$client] = $this->identity($bucket);

        $client->putObject(['Bucket' => $bucket, 'Key' => 'mine.txt', 'Body' => 'mine', 'ACL' => 'public-read', 'ContentType' => 'text/plain']);
        $this->assertSame('mine', (string) $client->getObject(['Bucket' => $bucket, 'Key' => 'mine.txt'])['Body']);
        $this->assertSame(['mine.txt'], array_column($client->listObjectsV2(['Bucket' => $bucket])['Contents'], 'Key'));
        $client->copyObject(['Bucket' => $bucket, 'Key' => 'copy.txt', 'CopySource' => "{$bucket}/mine.txt"]);
        $client->upload($bucket, 'big.bin', random_bytes(17 * 1024 * 1024), 'public-read');
        $client->deleteObject(['Bucket' => $bucket, 'Key' => 'mine.txt']);
        $client->deleteObjects(['Bucket' => $bucket, 'Delete' => ['Objects' => [['Key' => 'copy.txt'], ['Key' => 'big.bin']]]]);
        $this->assertEmpty($this->s3->listObjectsV2(['Bucket' => $bucket])['Contents'] ?? []);
    }

    public function testALimitedIdentityIsDeniedInAnotherTenantsBucket(): void
    {
        $alpha = $this->bucket('alpha');
        $beta = $this->bucket('beta');
        $this->s3->putObject(['Bucket' => $beta, 'Key' => 'secret.txt', 'Body' => 'beta only']);
        [$client] = $this->identity($alpha);

        $this->assertSame(403, $this->statusOf(fn () => $client->getObject(['Bucket' => $beta, 'Key' => 'secret.txt'])), 'GetObject');
        $this->assertSame(403, $this->statusOf(fn () => $client->headObject(['Bucket' => $beta, 'Key' => 'secret.txt'])), 'HeadObject');
        $this->assertSame(403, $this->statusOf(fn () => $client->listObjectsV2(['Bucket' => $beta])), 'ListObjectsV2');
        $this->assertSame(403, $this->statusOf(fn () => $client->putObject(['Bucket' => $beta, 'Key' => 'planted.txt', 'Body' => 'x'])), 'PutObject');
        $this->assertSame(403, $this->statusOf(fn () => $client->deleteObject(['Bucket' => $beta, 'Key' => 'secret.txt'])), 'DeleteObject');
        $this->assertSame(403, $this->statusOf(fn () => $client->copyObject(['Bucket' => $alpha, 'Key' => 'stolen.txt', 'CopySource' => "{$beta}/secret.txt"])), 'CopyObject from');
        $this->assertSame(403, $this->statusOf(fn () => $client->putBucketPolicy(['Bucket' => $beta, 'Policy' => '{}'])), 'PutBucketPolicy');
        $this->assertSame(403, $this->statusOf(fn () => $client->deleteBucket(['Bucket' => $beta])), 'DeleteBucket');
        $this->assertSame(403, $this->statusOf(fn () => $client->createBucket(['Bucket' => "compat-new-{$this->suffix}"])), 'CreateBucket');

        $this->assertSame('beta only', (string) $this->s3->getObject(['Bucket' => $beta, 'Key' => 'secret.txt'])['Body'], 'the object is untouched');
        $this->assertSame(['secret.txt'], array_column($this->s3->listObjectsV2(['Bucket' => $beta])['Contents'], 'Key'));
    }

    public function testALimitedIdentityDoesNotSeeOtherBucketsInTheListing(): void
    {
        $alpha = $this->bucket('alpha');
        $this->bucket('beta');
        [$client] = $this->identity($alpha);

        try {
            $names = array_column($client->listBuckets()['Buckets'], 'Name');
        } catch (S3Exception $exception) {
            $this->assertSame(403, $exception->getStatusCode());

            return;
        }
        $this->assertNotContains("compat-beta-{$this->suffix}", $names);
    }

    public function testARevokedAndARotatedKeyStopWorking(): void
    {
        $bucket = $this->bucket('rotate');
        [$client, $oldKey] = $this->identity($bucket);
        $client->putObject(['Bucket' => $bucket, 'Key' => 'f.txt', 'Body' => 'x']);
        $user = "compat-{$bucket}";

        $new = $this->iam()->createAccessKey(['UserName' => $user])['AccessKey'];
        $this->iam()->deleteAccessKey(['UserName' => $user, 'AccessKeyId' => $oldKey]);

        $this->assertContains($this->statusOf(fn () => $client->getObject(['Bucket' => $bucket, 'Key' => 'f.txt'])), [403, 401], 'the old key is refused');
        $fresh = $this->client($new['AccessKeyId'], $new['SecretAccessKey']);
        $this->assertSame('x', (string) $fresh->getObject(['Bucket' => $bucket, 'Key' => 'f.txt'])['Body']);

        $this->iam()->deleteAccessKey(['UserName' => $user, 'AccessKeyId' => $new['AccessKeyId']]);
        $this->iam()->deleteUser(['UserName' => $user]);
        $this->assertContains($this->statusOf(fn () => $fresh->getObject(['Bucket' => $bucket, 'Key' => 'f.txt'])), [403, 401], 'a deleted identity is refused');
    }

    public function testCreatingAUserTwiceIsRecognisable(): void
    {
        $this->requireSeaweedFs();
        $user = "compat-dup-{$this->suffix}";
        $this->users[] = $user;
        $this->iam()->createUser(['UserName' => $user]);

        try {
            $this->iam()->createUser(['UserName' => $user]);
            $this->fail('a second CreateUser for the same name must fail so the provisioner can tell it exists');
        } catch (\Aws\Exception\AwsException $exception) {
            $this->assertSame('EntityAlreadyExists', $exception->getAwsErrorCode());
        }
    }
}
