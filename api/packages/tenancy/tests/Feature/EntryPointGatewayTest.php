<?php

namespace Ulams\Tenancy\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Ulams\Tenancy\Exceptions\EntryPointException;
use Ulams\Tenancy\Models\EntryPoint;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;
use Ulams\Tenancy\Services\PlatformEntryPointGateway;
use Ulams\Tenancy\Support\TenantNaming;
use Ulams\Tenancy\Tests\TestCase;

/**
 * The process boundary between a tenant process and the platform table (ADR 0195): the gateway sends one
 * artisan call, the platform answers with one marked JSON line. The runner here executes the command in this
 * process, as the platform, instead of starting a child.
 */
class EntryPointGatewayTest extends TestCase
{
    /** @var list<array{host: string, arguments: list<string>}> */
    private array $calls = [];

    private string $output = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_tenancy.entry_trusted_suffixes' => ['.example.test'], 'ulams_tenancy.platform_command_host' => 'api.localhost']);
        Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes('coffee'), ['status' => Tenant::STATUS_ACTIVE]));
    }

    private function gateway(?callable $rewrite = null): PlatformEntryPointGateway
    {
        $test = $this;
        $runner = new class($test, $rewrite) implements TenantCommandRunnerContract {
            public function __construct(private EntryPointGatewayTest $test, private $rewrite)
            {
            }

            public function run(string $host, array $arguments): string
            {
                return $this->test->runAsPlatform($host, $arguments, $this->rewrite);
            }
        };

        return new PlatformEntryPointGateway($runner);
    }

    /** @internal */
    public function runAsPlatform(string $host, array $arguments, ?callable $rewrite): string
    {
        $this->calls[] = ['host' => $host, 'arguments' => $arguments];
        $command = array_shift($arguments);
        $parameters = ['slug' => $arguments[0], 'operation' => $arguments[1]];
        foreach (array_slice($arguments, 2) as $option) {
            [$name, $value] = explode('=', ltrim($option, '-'), 2);
            $parameters['--' . $name] = $value;
        }
        $slug = config('ulams_tenancy.tenant_slug');
        config(['ulams_tenancy.tenant_slug' => null]);
        try {
            $status = Artisan::call($command, $parameters);
        } finally {
            config(['ulams_tenancy.tenant_slug' => $slug]);
        }
        $output = Artisan::output();
        if ($status !== 0) {
            throw new RuntimeException("{$command} failed: {$output}");
        }

        return $rewrite ? $rewrite($output) : $output;
    }

    public function testARoundTripThroughTheCommand(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);
        $gateway = $this->gateway();

        $created = $gateway->create('coffee', ['host' => 'sales.coffee.example.test', 'key' => 'sales', 'kind' => 'role', 'target' => ['role' => 'manager'], 'label' => 'Managers'], ['id' => 4, 'email' => 'a@coffee.test']);
        $this->assertSame(['active', ['role' => 'manager']], [$created['status'], $created['target']]);

        $this->assertSame([$created['id']], array_column($gateway->list('coffee'), 'id'));
        $this->assertSame('Managers', $gateway->show('coffee', $created['id'])['label']);
        $this->assertSame('Leads', $gateway->update('coffee', $created['id'], ['label' => 'Leads'], null)['label']);
        $this->assertSame('a@coffee.test', $gateway->audit('coffee')[1]['actor_email']);
        $gateway->delete('coffee', $created['id'], null);
        $this->assertSame(0, EntryPoint::query()->count());

        // every call went to the platform host, with the tenant as an argument
        $this->assertSame(['api.localhost'], array_values(array_unique(array_column($this->calls, 'host'))));
        $this->assertSame('coffee', $this->calls[0]['arguments'][1]);
        $this->assertSame('ulams:entry-points:gateway', $this->calls[0]['arguments'][0]);
    }

    public function testARefusalKeepsItsStatusAndFieldErrors(): void
    {
        $gateway = $this->gateway();

        try {
            $gateway->create('coffee', ['host' => '10.0.0.1', 'key' => 'k', 'kind' => 'academy', 'label' => 'x'], null);
            $this->fail('Accepted an address as a host.');
        } catch (EntryPointException $e) {
            $this->assertSame(422, $e->status);
            $this->assertArrayHasKey('host', $e->errors);
        }
        try {
            $gateway->show('coffee', '01j9z3k8m2x4q7r5t6v8w0y1ab');
            $this->fail('Found an entry point that does not exist.');
        } catch (EntryPointException $e) {
            $this->assertSame(404, $e->status);
        }
        try {
            $gateway->list('nobody');
            $this->fail('Listed an unknown academy.');
        } catch (EntryPointException $e) {
            $this->assertSame(404, $e->status);
        }
    }

    public function testNoiseAroundTheReplyIsIgnored(): void
    {
        $gateway = $this->gateway(fn (string $output) => "Deprecated: something\n{$output}\nnotice: trailing");

        $this->assertSame([], $gateway->list('coffee'));
    }

    public function testAnAnswerWithoutTheMarkerIsAFailureNotAnEmptyList(): void
    {
        $gateway = $this->gateway(fn () => 'PHP Fatal error: boom');

        $this->expectException(RuntimeException::class);
        $gateway->list('coffee');
    }

    public function testAFailingChildProcessIsAFailure(): void
    {
        $runner = new class implements TenantCommandRunnerContract {
            public function run(string $host, array $arguments): string
            {
                throw new RuntimeException('exit 1');
            }
        };

        $this->expectException(RuntimeException::class);
        (new PlatformEntryPointGateway($runner))->list('coffee');
    }

    public function testTheCommandRefusesToRunInATenantProcess(): void
    {
        config(['ulams_tenancy.tenant_slug' => 'coffee']);

        $this->assertSame(1, Artisan::call('ulams:entry-points:gateway', ['slug' => 'coffee', 'operation' => 'list']));
        $this->assertStringNotContainsString(PlatformEntryPointGateway::MARKER, Artisan::output());
    }

    public function testAnUnknownOperationIsRefused(): void
    {
        $this->assertSame(0, Artisan::call('ulams:entry-points:gateway', ['slug' => 'coffee', 'operation' => 'drop-everything']));
        $this->assertStringContainsString('"status":400', Artisan::output());
    }

    public function testTheTenantComesFromTheArgumentNotThePayload(): void
    {
        Tenant::query()->create(array_merge(TenantNaming::newTenantAttributes('tea'), ['status' => Tenant::STATUS_ACTIVE]));

        $payload = base64_encode(json_encode(['data' => ['host' => 'x.tea.example.test', 'key' => 'x', 'kind' => 'academy', 'label' => 'X'], 'tenant' => 'tea', 'slug' => 'tea', 'tenant_id' => 2]));
        Artisan::call('ulams:entry-points:gateway', ['slug' => 'coffee', 'operation' => 'create', '--payload' => $payload]);

        $this->assertSame('coffee', Tenant::query()->findOrFail(EntryPoint::query()->firstOrFail()->tenant_id)->slug);
    }
}
