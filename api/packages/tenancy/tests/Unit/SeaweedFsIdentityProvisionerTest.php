<?php

namespace Ulams\Tenancy\Tests\Unit;

use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Iam\IamClient;
use Aws\MockHandler;
use Aws\Result;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ulams\Tenancy\Services\SeaweedFsIdentityProvisioner;
use Ulams\Tenancy\Storage\StorageCredentials;

class SeaweedFsIdentityProvisionerTest extends TestCase
{
    private MockHandler $responses;
    /** @var list<array{name: string, args: array}> */
    private array $commands = [];
    private SeaweedFsIdentityProvisioner $provisioner;

    protected function setUp(): void
    {
        $this->responses = new MockHandler();
        $client = new IamClient([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => 'http://storage:8333',
            'credentials' => ['key' => 'admin', 'secret' => 'admin-secret'],
            'handler' => function (CommandInterface $command, $request) {
                $this->commands[] = ['name' => $command->getName(), 'args' => $command->toArray()];

                return ($this->responses)($command, $request);
            },
        ]);
        $this->provisioner = new SeaweedFsIdentityProvisioner($client);
    }

    private function names(): array
    {
        return array_column($this->commands, 'name');
    }

    private function error(string $code, int $status = 409, string $secretInBody = 'LEAKED-SECRET'): callable
    {
        return fn (CommandInterface $command) => new AwsException("body: {$secretInBody}", $command, [
            'code' => $code,
            'response' => new \GuzzleHttp\Psr7\Response($status, [], "<Error><Code>{$code}</Code><Secret>{$secretInBody}</Secret></Error>"),
            'request' => new Request('POST', 'http://storage:8333/'),
        ]);
    }

    private function keys(string ...$ids): Result
    {
        return new Result(['AccessKeyMetadata' => array_map(fn (string $id) => ['UserName' => 'ulams-acme', 'AccessKeyId' => $id, 'Status' => 'Active'], $ids)]);
    }

    private function newKey(string $id = 'AKIANEWKEY0123456789', string $secret = 'new-secret'): Result
    {
        return new Result(['AccessKey' => ['UserName' => 'ulams-acme', 'AccessKeyId' => $id, 'SecretAccessKey' => $secret, 'Status' => 'Active']]);
    }

    public function testEnsureCreatesTheIdentityPolicyAndKeyForANewTenant(): void
    {
        $this->responses->append(new Result(), new Result(), $this->newKey(), $this->keys('AKIANEWKEY0123456789'));

        $credentials = $this->provisioner->ensure('acme', 'ulams-acme');

        $this->assertSame('AKIANEWKEY0123456789', $credentials->accessKey);
        $this->assertSame('new-secret', $credentials->secret());
        $this->assertSame(['CreateUser', 'PutUserPolicy', 'CreateAccessKey', 'ListAccessKeys'], $this->names());
        $this->assertSame('ulams-acme', $this->commands[0]['args']['UserName']);
    }

    public function testThePolicyGrantsTheTenantBucketAndNothingElse(): void
    {
        $this->responses->append(new Result(), new Result(), $this->newKey(), $this->keys('AKIANEWKEY0123456789'));

        $this->provisioner->ensure('acme', 'ulams-acme');

        $policy = json_decode($this->commands[1]['args']['PolicyDocument'], true);
        $this->assertCount(1, $policy['Statement']);
        $this->assertSame('Allow', $policy['Statement'][0]['Effect']);
        $this->assertSame(['arn:aws:s3:::ulams-acme', 'arn:aws:s3:::ulams-acme/*'], $policy['Statement'][0]['Resource']);
        $actions = $policy['Statement'][0]['Actions'] ?? $policy['Statement'][0]['Action'];
        $this->assertNotContains('s3:*', $actions);
        $this->assertNotContains('s3:PutBucketPolicy', $actions);
        $this->assertNotContains('s3:DeleteBucket', $actions);
        $this->assertNotContains('s3:CreateBucket', $actions);
        foreach (['s3:GetObject', 's3:PutObject', 's3:DeleteObject', 's3:ListBucket', 's3:AbortMultipartUpload'] as $needed) {
            $this->assertContains($needed, $actions);
        }
    }

    public function testEnsureKeepsAKeyTheStoreStillKnows(): void
    {
        $this->responses->append($this->error('EntityAlreadyExists'), new Result(), $this->keys('AKIAOLDKEY0123456789'));

        $credentials = $this->provisioner->ensure('acme', 'ulams-acme', new StorageCredentials('AKIAOLDKEY0123456789', 'old-secret'));

        $this->assertSame('AKIAOLDKEY0123456789', $credentials->accessKey);
        $this->assertSame('old-secret', $credentials->secret());
        $this->assertNotContains('CreateAccessKey', $this->names());
        $this->assertNotContains('DeleteAccessKey', $this->names());
    }

    public function testEnsureIssuesANewKeyWhenTheStoredOneIsUnknownAndDropsTheRest(): void
    {
        $this->responses->append(
            $this->error('EntityAlreadyExists'),
            new Result(),
            $this->keys('AKIASTALE0123456789'),
            $this->newKey('AKIAFRESH0123456789', 'fresh-secret'),
            $this->keys('AKIASTALE0123456789', 'AKIAFRESH0123456789'),
            new Result()
        );

        $credentials = $this->provisioner->ensure('acme', 'ulams-acme', new StorageCredentials('AKIALOST0123456789', 'lost'));

        $this->assertSame('AKIAFRESH0123456789', $credentials->accessKey);
        $deleted = array_values(array_filter($this->commands, fn ($c) => $c['name'] === 'DeleteAccessKey'));
        $this->assertCount(1, $deleted);
        $this->assertSame('AKIASTALE0123456789', $deleted[0]['args']['AccessKeyId']);
    }

    public function testRotateAddsAKeyAndLeavesTheOldOnesWorking(): void
    {
        $this->responses->append($this->error('EntityAlreadyExists'), new Result(), $this->newKey('AKIAROTATED0123456789', 'rotated-secret'));

        $credentials = $this->provisioner->rotate('acme', 'ulams-acme');

        $this->assertSame('AKIAROTATED0123456789', $credentials->accessKey);
        $this->assertNotContains('DeleteAccessKey', $this->names());
    }

    public function testPruneDeletesEveryKeyExceptTheOneInUse(): void
    {
        $this->responses->append($this->keys('AKIAOLDKEY0123456789ONE0123456789', 'AKIACURRENT0123456789', 'AKIAOLDKEY0123456789TWO0123456789'), new Result(), new Result());

        $this->provisioner->prune('acme', new StorageCredentials('AKIACURRENT0123456789', 's'));

        $deleted = array_column(array_filter($this->commands, fn ($c) => $c['name'] === 'DeleteAccessKey'), 'args');
        $this->assertSame(['AKIAOLDKEY0123456789ONE0123456789', 'AKIAOLDKEY0123456789TWO0123456789'], array_column($deleted, 'AccessKeyId'));
    }

    public function testRevokeRemovesKeysPolicyAndIdentity(): void
    {
        $this->responses->append($this->keys('AKIAONE01234567890'), new Result(), new Result(), new Result());

        $this->provisioner->revoke('acme');

        $this->assertSame(['ListAccessKeys', 'DeleteAccessKey', 'DeleteUserPolicy', 'DeleteUser'], $this->names());
    }

    public function testRevokeOfAMissingIdentityIsIgnored(): void
    {
        $this->responses->append($this->error('NoSuchEntity', 404));

        $this->provisioner->revoke('gone');

        $this->assertSame(['ListAccessKeys'], $this->names());
    }

    public function testAnApiFailureNamesTheActionAndCodeButNeverTheResponseBody(): void
    {
        $this->responses->append(new Result(), new Result(), $this->error('AccessDenied', 403, 'LEAKED-SECRET'));

        try {
            $this->provisioner->ensure('acme', 'ulams-acme');
            $this->fail('the failure must surface');
        } catch (RuntimeException $exception) {
            $this->assertSame('Object store IAM CreateAccessKey failed: AccessDenied (HTTP 403).', $exception->getMessage());
            $this->assertStringNotContainsString('LEAKED-SECRET', (string) $exception);
            $this->assertNull($exception->getPrevious(), 'the SDK exception carries the request and response');
        }
    }

    public function testCredentialsDoNotShowTheSecretWhenDumpedOrEncoded(): void
    {
        $credentials = new StorageCredentials('AKIAKEY', 'very-secret-value');

        $this->assertStringNotContainsString('very-secret-value', var_export($credentials, true));
        $this->assertStringNotContainsString('very-secret-value', print_r($credentials, true));
        $this->assertStringNotContainsString('very-secret-value', json_encode($credentials));
        $this->assertStringNotContainsString('very-secret-value', (string) $credentials);
        $this->assertStringNotContainsString('very-secret-value', serialize($credentials));
        $this->assertSame('very-secret-value', $credentials->secret());
    }

    public function testTheUserNameIsDerivedFromTheSlug(): void
    {
        $this->assertSame('ulams-acme', SeaweedFsIdentityProvisioner::userName('acme'));
    }
}
