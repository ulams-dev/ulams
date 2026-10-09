<?php

namespace Ulams\Tenancy\Tests\Unit;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Ulams\Tenancy\Services\S3BucketProvisioner;

class S3BucketProvisionerTest extends TestCase
{
    private MockHandler $handler;
    /** @var list<string> */
    private array $commands = [];
    private S3BucketProvisioner $provisioner;

    protected function setUp(): void
    {
        $this->handler = new MockHandler([], function () {
        });
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => 'http://minio:9000',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'handler' => function (CommandInterface $command, $request) {
                $this->commands[] = $command->getName() . ':' . ($command['Bucket'] ?? '');
                if ($command->getName() === 'PutBucketPolicy') {
                    $this->commands[] = 'policy:' . $command['Policy'];
                }

                return ($this->handler)($command, $request);
            },
        ]);
        $this->provisioner = new S3BucketProvisioner($client);
    }

    private function notFound(): callable
    {
        return fn (CommandInterface $command) => new S3Exception('Not Found', $command, [
            'response' => new Response(404),
        ]);
    }

    public function testCreatesMissingBucketWithPublicReadPolicy(): void
    {
        $this->handler->append($this->notFound(), new Result(), new Result());

        $this->provisioner->ensure('ulams-coffee');

        $this->assertSame(['HeadBucket:ulams-coffee', 'CreateBucket:ulams-coffee', 'PutBucketPolicy:ulams-coffee'], array_slice($this->commands, 0, 3));
        $policy = json_decode(substr($this->commands[3], strlen('policy:')), true);
        $this->assertSame(['s3:GetObject'], $policy['Statement'][0]['Action']);
        $this->assertSame(['arn:aws:s3:::ulams-coffee/*'], $policy['Statement'][0]['Resource']);
    }

    public function testNoPolicyIsAttachedWhenTheStoreHasNone(): void
    {
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'handler' => function (CommandInterface $command, $request) {
                $this->commands[] = $command->getName() . ':' . ($command['Bucket'] ?? '');

                return ($this->handler)($command, $request);
            },
        ]);
        $this->handler->append($this->notFound(), new Result());

        (new S3BucketProvisioner($client, publicReadPolicy: false))->ensure('ulams-coffee');

        $this->assertSame(['HeadBucket:ulams-coffee', 'CreateBucket:ulams-coffee'], $this->commands);
    }

    public function testConfigTurnsThePolicyOff(): void
    {
        $this->assertInstanceOf(S3BucketProvisioner::class, S3BucketProvisioner::fromConfig(['public_read_policy' => false, 'endpoint' => 'http://x']));
    }

    public function testExistingBucketIsNotCreatedAgain(): void
    {
        $this->handler->append(new Result(), new Result());

        $this->provisioner->ensure('ulams-coffee');

        $this->assertSame(['HeadBucket:ulams-coffee', 'PutBucketPolicy:ulams-coffee'], array_slice($this->commands, 0, 2));
    }

    public function testDeleteIgnoresMissingBucket(): void
    {
        $this->handler->append($this->notFound());

        $this->provisioner->delete('ulams-gone');

        $this->assertSame(['HeadBucket:ulams-gone'], $this->commands);
    }

    public function testDeleteEmptiesAndRemovesTheBucket(): void
    {
        $this->handler->append(
            new Result(),
            new Result(['Contents' => [['Key' => 'a.png'], ['Key' => 'b.png']], 'IsTruncated' => false]),
            new Result(['Deleted' => [['Key' => 'a.png'], ['Key' => 'b.png']]]),
            new Result()
        );

        $this->provisioner->delete('ulams-old');

        $this->assertSame('HeadBucket:ulams-old', $this->commands[0]);
        $this->assertContains('DeleteObjects:ulams-old', $this->commands);
        $this->assertSame('DeleteBucket:ulams-old', end($this->commands));
    }
}
