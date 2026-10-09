<?php

namespace Ulams\Tenancy\Services;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;

class S3BucketProvisioner implements BucketProvisionerContract
{
    public function __construct(private S3Client $client)
    {
    }

    public static function fromConfig(array $config): self
    {
        return new self(new S3Client(array_filter([
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint' => isset($config['endpoint']) ? trim((string) $config['endpoint'], '"') : null,
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => (string) ($config['key'] ?? ''),
                'secret' => (string) ($config['secret'] ?? ''),
            ],
        ], fn ($value) => $value !== null)));
    }

    public function ensure(string $bucket): void
    {
        if (!$this->exists($bucket)) {
            $this->client->createBucket(['Bucket' => $bucket]);
        }

        $this->client->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => json_encode(self::publicReadPolicy($bucket)),
        ]);
    }

    public function delete(string $bucket): void
    {
        if (!$this->exists($bucket)) {
            return;
        }

        $this->client->deleteMatchingObjects($bucket, '', '/.*/s');
        $this->client->deleteBucket(['Bucket' => $bucket]);
    }

    public static function publicReadPolicy(string $bucket): array
    {
        return [
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Principal' => ['AWS' => ['*']],
                'Action' => ['s3:GetObject'],
                'Resource' => ["arn:aws:s3:::{$bucket}/*"],
            ]],
        ];
    }

    private function exists(string $bucket): bool
    {
        try {
            $this->client->headBucket(['Bucket' => $bucket]);

            return true;
        } catch (S3Exception $exception) {
            if ($exception->getStatusCode() === 404) {
                return false;
            }
            throw $exception;
        }
    }
}
