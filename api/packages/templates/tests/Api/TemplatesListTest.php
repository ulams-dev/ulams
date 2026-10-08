<?php

namespace EscolaLms\Templates\Tests\Api;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Contracts\TemplateChannelContract;
use EscolaLms\Templates\Contracts\TemplateVariableContract;
use EscolaLms\Templates\Facades\Template as FacadesTemplate;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Tests\Mock\TestChannel;
use EscolaLms\Templates\Tests\Mock\TestEventWithGettersAndToArray;
use EscolaLms\Templates\Tests\Mock\TestVariablesWithAssignableClass;
use EscolaLms\Templates\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TemplatesListTest extends TestCase
{
    use DatabaseTransactions;

    private string $uri = '/api/templates';

    protected function setUp(): void
    {
        parent::setUp();

        FacadesTemplate::register(TestEventWithGettersAndToArray::class, TestChannel::class, TestVariablesWithAssignableClass::class);
    }

    public function testAdminCanListEmpty(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/templates');
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data',
            'meta',
            'message'
        ]);
        $response->assertJsonCount(0, 'data');
    }

    public function testAdminCanList(): void
    {
        $this->authenticateAsAdmin();

        $templates = Template::factory()
            ->count(10)
            ->create();

        $templatesArr = $templates->map(function (Template $p) {
            return $p->toArray();
        })->toArray();

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/templates');
        $response->assertOk();
        $response->assertJsonFragment(
            $templatesArr[0],
        );
    }

    public function testAdminCanListWithPerPage(): void
    {
        $this->authenticateAsAdmin();

        $templates = Template::factory()
            ->count(10)
            ->create();

        $templatesArr = $templates->map(function (Template $p) {
            return $p->toArray();
        })->toArray();

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/templates/?per_page=5');
        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonFragment(
            $templatesArr[0],
        );
    }

    public function testGuestCannotList(): void
    {
        $response = $this->getJson('/api/admin/templates');

        $response->assertUnauthorized();
    }

    public function testAdminCanListWithFilters()
    {
        $this->authenticateAsAdmin();

        $templates = Template::factory()
            ->count(9)
            ->create();


        $template = Template::factory()->create([
            'name' => 'test one',
            'event' => TestEventWithGettersAndToArray::class,
            'channel' => TestChannel::class
        ]);

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'name' => 'one',
            'event' => TestEventWithGettersAndToArray::class,
            'channel' => TestChannel::class,
        ]);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'data' => [
                array_merge(
                    $template->toArray(),
                    [
                        'is_assignable' => true,
                        'assignable_class' => User::class,
                        'variable_class' => TestVariablesWithAssignableClass::class,
                        'assignables' => [],
                        'sections' => []
                    ]
                )
            ]
        ]);
    }

    public function testAdminCanListWithSorts()
    {
        $this->authenticateAsAdmin();

        $templateOne = Template::factory()->create([
            'name' => 'first test',
            'channel' => TemplateChannelContract::class,
            'event' => TemplateVariableContract::class,
            'created_at' => now()->subDay(),
            'default' => false,
        ]);

        $templateTwo = Template::factory()->create([
            'name' => 'second test',
            'event' => TestEventWithGettersAndToArray::class,
            'channel' => TestChannel::class,
            'created_at' => now()->addDay(),
            'default' => true,
        ]);

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'created_at',
            'order' => 'desc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'created_at',
            'order' => 'asc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'name',
            'order' => 'desc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'name',
            'order' => 'asc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'default',
            'order' => 'desc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'default',
            'order' => 'asc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'event',
            'order' => 'desc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'event',
            'order' => 'asc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'channel',
            'order' => 'desc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/templates', [
            'order_by' => 'channel',
            'order' => 'asc'
        ]);

        $this->assertTrue($response->json('data.0.id') === $templateOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $templateTwo->getKey());
    }
}
