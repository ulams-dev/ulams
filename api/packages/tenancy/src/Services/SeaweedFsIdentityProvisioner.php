<?php

namespace Ulams\Tenancy\Services;

use Aws\Exception\AwsException;
use Aws\Iam\IamClient;
use RuntimeException;
use Ulams\Tenancy\Services\Contracts\StorageIdentityProvisionerContract;
use Ulams\Tenancy\Storage\StorageCredentials;

/**
 * Per-tenant identities through SeaweedFS's embedded IAM API (`CreateUser`, `CreateAccessKey`,
 * `PutUserPolicy` on the S3 port, signed with the admin key; the store runs with `-s3.iam.readOnly=false`).
 *
 * The identity is named `ulams-<slug>` and its policy allows object and multipart operations on the
 * tenant's bucket only, so its keys read, write and list that bucket and get 403 on every other one.
 * Bucket creation and policy changes stay with the admin key (`S3BucketProvisioner`).
 *
 * Failures are reported with the action and the AWS error code only: the SDK exception carries the
 * request and response, and a CreateAccessKey response holds a secret.
 */
class SeaweedFsIdentityProvisioner implements StorageIdentityProvisionerContract
{
    public const USER_PREFIX = 'ulams-';

    /**
     * What the application does to a tenant bucket (ADR 0041). No bucket policy, no bucket creation or
     * deletion. SeaweedFS rejects `s3:ListBucketMultipartUploads` and `s3:ListMultipartUploadParts` as
     * invalid actions; multipart uploads work without them (checked by the S3 compatibility suite).
     */
    public const ACTIONS = [
        's3:GetObject', 's3:PutObject', 's3:DeleteObject',
        's3:GetObjectAcl', 's3:PutObjectAcl',
        's3:GetObjectTagging', 's3:PutObjectTagging', 's3:DeleteObjectTagging',
        's3:ListBucket', 's3:GetBucketLocation',
        's3:AbortMultipartUpload',
    ];

    public function __construct(private IamClient $iam)
    {
    }

    /** @param array{endpoint?: ?string, region?: ?string, key?: ?string, secret?: ?string} $config */
    public static function fromConfig(array $config): self
    {
        return new self(new IamClient([
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint' => trim((string) ($config['endpoint'] ?? ''), '"'),
            'credentials' => [
                'key' => (string) ($config['key'] ?? ''),
                'secret' => (string) ($config['secret'] ?? ''),
            ],
        ]));
    }

    public static function userName(string $slug): string
    {
        return self::USER_PREFIX . $slug;
    }

    public static function policy(string $bucket): array
    {
        return [
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Action' => self::ACTIONS,
                'Resource' => ["arn:aws:s3:::{$bucket}", "arn:aws:s3:::{$bucket}/*"],
            ]],
        ];
    }

    public function ensure(string $slug, string $bucket, ?StorageCredentials $current = null): ?StorageCredentials
    {
        $user = $this->prepareUser($slug, $bucket);

        if ($current !== null && in_array($current->accessKey, $this->accessKeyIds($user), true)) {
            return $current;
        }

        $issued = $this->issueKey($user);
        // the secret of any key already there is unknown to us: only the new one is kept
        $this->prune($slug, $issued);

        return $issued;
    }

    public function rotate(string $slug, string $bucket): ?StorageCredentials
    {
        return $this->issueKey($this->prepareUser($slug, $bucket));
    }

    public function prune(string $slug, StorageCredentials $keep): void
    {
        $user = self::userName($slug);
        foreach ($this->accessKeyIds($user) as $accessKey) {
            if ($accessKey !== $keep->accessKey) {
                $this->call('DeleteAccessKey', fn () => $this->iam->deleteAccessKey(['UserName' => $user, 'AccessKeyId' => $accessKey]));
            }
        }
    }

    public function revoke(string $slug): void
    {
        $user = self::userName($slug);
        try {
            foreach ($this->accessKeyIds($user) as $accessKey) {
                $this->call('DeleteAccessKey', fn () => $this->iam->deleteAccessKey(['UserName' => $user, 'AccessKeyId' => $accessKey]));
            }
            $this->call('DeleteUserPolicy', fn () => $this->iam->deleteUserPolicy(['UserName' => $user, 'PolicyName' => 'bucket']));
            $this->call('DeleteUser', fn () => $this->iam->deleteUser(['UserName' => $user]));
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), 'NoSuchEntity')) {
                throw $exception;
            }
        }
    }

    /** Creates the identity when missing and (re)applies its bucket policy; returns the IAM user name. */
    private function prepareUser(string $slug, string $bucket): string
    {
        $user = self::userName($slug);
        $this->createUserIfMissing($user);
        $this->call('PutUserPolicy', fn () => $this->iam->putUserPolicy([
            'UserName' => $user,
            'PolicyName' => 'bucket',
            'PolicyDocument' => json_encode(self::policy($bucket)),
        ]));

        return $user;
    }

    private function createUserIfMissing(string $user): void
    {
        try {
            $this->call('CreateUser', fn () => $this->iam->createUser(['UserName' => $user]));
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), 'EntityAlreadyExists')) {
                throw $exception;
            }
        }
    }

    private function issueKey(string $user): StorageCredentials
    {
        $result = $this->call('CreateAccessKey', fn () => $this->iam->createAccessKey(['UserName' => $user]));
        $key = $result['AccessKey'] ?? [];
        if (empty($key['AccessKeyId']) || empty($key['SecretAccessKey'])) {
            throw new RuntimeException('Object store IAM: CreateAccessKey returned no key.');
        }

        return new StorageCredentials((string) $key['AccessKeyId'], (string) $key['SecretAccessKey']);
    }

    /** @return list<string> */
    private function accessKeyIds(string $user): array
    {
        $result = $this->call('ListAccessKeys', fn () => $this->iam->listAccessKeys(['UserName' => $user]));

        return array_values(array_map(
            fn (array $key): string => (string) $key['AccessKeyId'],
            (array) ($result['AccessKeyMetadata'] ?? [])
        ));
    }

    /**
     * @template T
     * @param callable(): T $request
     * @return T
     */
    private function call(string $action, callable $request): mixed
    {
        try {
            return $request();
        } catch (AwsException $exception) {
            throw new RuntimeException(sprintf(
                'Object store IAM %s failed: %s (HTTP %d).',
                $action,
                $exception->getAwsErrorCode() ?? 'no error code',
                $exception->getStatusCode() ?? 0
            ));
        }
    }
}
