<?php

namespace Ulams\Core\Tests\Mocks;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelS3Adapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Psr\Http\Message\RequestInterface;

/**
 * An S3 bucket in memory behind the real `s3` disk driver (the AWS SDK client and the Flysystem S3
 * adapter), for tests of code that must work when `Storage::path()` is not a place on this machine and the
 * public URL does not resolve. No network, no container.
 *
 *   $s3 = FakeS3::install();            // registers the disk `s3` and makes it the default
 *   Storage::put('a/b.txt', 'x');       // lands in $s3->objects['a/b.txt']
 */
final class FakeS3
{
    /** @var array<string, array{body: string, type: ?string, acl: ?string}> key => object */
    public array $objects = [];

    /** @var list<string> every command that reached the "store", for assertions */
    public array $commands = [];

    /** @var array<string, int> command name => HTTP status to answer instead of working (a store without the operation) */
    public array $failing = [];

    public function __construct(public readonly string $bucket = 'fake-bucket')
    {
    }

    /** Registers the disk under `$name` and makes it the default disk. */
    public static function install(string $name = 's3', string $bucket = 'fake-bucket'): self
    {
        $fake = new self($bucket);
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => 'http://storage.invalid',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'handler' => fn (CommandInterface $command, RequestInterface $request) => $fake->handle($command),
        ]);
        $adapter = new AwsS3V3Adapter($client, $bucket);
        $config = ['driver' => 's3', 'bucket' => $bucket, 'url' => 'http://storage.invalid/' . $bucket];
        Storage::set($name, new LaravelS3Adapter(new Filesystem($adapter), $adapter, $config, $client));
        config(['filesystems.default' => $name, "filesystems.disks.{$name}" => $config]);

        return $fake;
    }

    public function handle(CommandInterface $command): PromiseInterface
    {
        $this->commands[] = $command->getName();
        if (isset($this->failing[$command->getName()])) {
            return Create::rejectionFor($this->error($command, 'InternalError', $this->failing[$command->getName()]));
        }

        $result = match ($command->getName()) {
            'PutObject' => $this->put($command),
            'GetObject' => $this->get($command),
            'HeadObject' => $this->head($command),
            'DeleteObject' => $this->delete($command),
            'DeleteObjects' => $this->deleteMany($command),
            'CopyObject' => $this->copy($command),
            'ListObjectsV2', 'ListObjects' => $this->list($command),
            'GetObjectAcl' => $this->acl($command),
            'PutObjectAcl', 'HeadBucket', 'CreateBucket', 'PutBucketPolicy' => new Result(),
            default => throw new \LogicException('FakeS3 does not implement ' . $command->getName()),
        };

        return $result instanceof \Throwable ? Create::rejectionFor($result) : Create::promiseFor($result);
    }

    private function acl(CommandInterface $command): Result|S3Exception
    {
        $object = $this->objects[$command['Key']] ?? null;
        if ($object === null) {
            return $this->error($command, 'NoSuchKey', 404);
        }

        return new Result(['Grants' => $object['acl'] === 'public-read'
            ? [['Grantee' => ['Type' => 'Group', 'URI' => 'http://acs.amazonaws.com/groups/global/AllUsers'], 'Permission' => 'READ']]
            : []]);
    }

    private function put(CommandInterface $command): Result
    {
        $body = $command['Body'] ?? '';
        $this->objects[$command['Key']] = [
            'body' => is_string($body) ? $body : (string) Utils::streamFor($body),
            'type' => $command['ContentType'] ?? null,
            'acl' => $command['ACL'] ?? null,
        ];

        return new Result(['ETag' => '"' . md5($this->objects[$command['Key']]['body']) . '"']);
    }

    private function get(CommandInterface $command): Result|S3Exception
    {
        $object = $this->objects[$command['Key']] ?? null;
        if ($object === null) {
            return $this->error($command, 'NoSuchKey', 404);
        }

        return new Result([
            'Body' => Utils::streamFor($object['body']),
            'ContentLength' => strlen($object['body']),
            'ContentType' => $object['type'],
        ]);
    }

    private function head(CommandInterface $command): Result|S3Exception
    {
        $object = $this->objects[$command['Key']] ?? null;

        return $object === null
            ? $this->error($command, 'NotFound', 404)
            : new Result(['ContentLength' => strlen($object['body']), 'ContentType' => $object['type'], 'LastModified' => gmdate('c')]);
    }

    private function delete(CommandInterface $command): Result
    {
        unset($this->objects[$command['Key']]);

        return new Result();
    }

    private function deleteMany(CommandInterface $command): Result
    {
        $deleted = [];
        foreach ($command['Delete']['Objects'] as $object) {
            unset($this->objects[$object['Key']]);
            $deleted[] = ['Key' => $object['Key']];
        }

        return new Result(['Deleted' => $deleted]);
    }

    private function copy(CommandInterface $command): Result|S3Exception
    {
        $source = ltrim((string) preg_replace('#^/?' . preg_quote($this->bucket, '#') . '/#', '', rawurldecode((string) $command['CopySource'])), '/');
        if (!isset($this->objects[$source])) {
            return $this->error($command, 'NoSuchKey', 404);
        }
        $this->objects[$command['Key']] = $this->objects[$source];

        return new Result(['CopyObjectResult' => ['ETag' => '"copy"']]);
    }

    private function list(CommandInterface $command): Result
    {
        $prefix = (string) ($command['Prefix'] ?? '');
        $delimiter = $command['Delimiter'] ?? null;
        $keys = array_values(array_filter(array_keys($this->objects), fn (string $key) => str_starts_with($key, $prefix)));
        sort($keys);

        $contents = [];
        $prefixes = [];
        foreach ($keys as $key) {
            $rest = substr($key, strlen($prefix));
            if ($delimiter !== null && ($position = strpos($rest, $delimiter)) !== false) {
                $prefixes[$prefix . substr($rest, 0, $position + 1)] = ['Prefix' => $prefix . substr($rest, 0, $position + 1)];
                continue;
            }
            $contents[] = ['Key' => $key, 'Size' => strlen($this->objects[$key]['body']), 'LastModified' => gmdate('c'), 'ETag' => '"x"'];
        }
        $max = (int) ($command['MaxKeys'] ?? 1000);

        // like S3, leave out what is empty: Flysystem asks hasKey('Contents') to tell a directory exists
        return new Result(array_filter([
            'Contents' => array_slice($contents, 0, $max),
            'CommonPrefixes' => array_values($prefixes),
        ]) + [
            'IsTruncated' => false,
            'KeyCount' => min($max, count($contents) + count($prefixes)),
        ]);
    }

    private function error(CommandInterface $command, string $code, int $status): S3Exception
    {
        return new S3Exception($code, $command, ['code' => $code, 'response' => new Response($status)]);
    }
}
