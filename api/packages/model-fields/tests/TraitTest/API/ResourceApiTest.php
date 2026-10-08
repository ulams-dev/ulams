<?php

namespace Ulams\ModelFields\Tests\TraitTest\API;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\ModelFields\Tests\TestCase;
use Ulams\ModelFields\Enum\MetaFieldTypeEnum;
use Ulams\ModelFields\Enum\MetaFieldVisibilityEnum;

use Ulams\Core\Enums\UserRole;
use Ulams\ModelFields\Tests\TraitTest\Models\User;
use Illuminate\Support\Facades\App;
use Ulams\ModelFields\Services\Contracts\ModelFieldsServiceContract;

use Ulams\ModelFields\Facades\ModelFields;


class ResourceApiTest extends TestCase
{

    use CreatesUsers;

    public function setUp(): void
    {

        parent::setUp();

        $this->service = App::make(ModelFieldsServiceContract::class);

        $this->service->addOrUpdateMetadataField(
            User::class,
            'description',
            'text',
            'lorem ipsum',
            ['required', 'string', 'max:255']
        );

        ModelFields::addOrUpdateMetadataField(
            User::class,
            'title',
            'varchar',
            '',
            ['required', 'string', 'max:255']
        );

        ModelFields::addOrUpdateMetadataField(
            User::class,
            'admin_secret',
            'varchar',
            'super_secret',
            ['required', 'string', 'max:255'],
            MetaFieldVisibilityEnum::ADMIN
        );

        User::create([
            'first_name' => 'aaa',
            'last_name' => 'aaa',
            'email' => 'aaa@email.com',
            'description' => 'aaa',
            'admin_secret' => 'XXX'
        ]);

        User::create([
            'first_name' => 'aaa',
            'last_name' => 'aaa',
            'email' => 'aaa1@email.com',
            'description' => 'aaa',
            'admin_secret' => 'XXX'
        ]);

        User::create([
            'first_name' => 'aaa',
            'last_name' => 'aaa',
            'email' => 'aaa2@email.com',
            'description' => 'aaa',
            'admin_secret' => 'XXX'
        ]);

        User::create([
            'first_name' => 'aaa',
            'last_name' => 'aaa',
            'email' => 'aaa3@email.com',
            'description' => 'aaa',
            'admin_secret' => 'XXX'
        ]);
    }

    public function testListResourceVisibility()
    {

        $result = $this->getJson('/api/trait/test-users');

        $this->assertTrue(!collect($result->getData())->contains(fn ($item) => isset($item->admin_secret) && $item->admin_secret === 'XXX'));

        $result = $this->getJson('/api/admin/trait/test-users');

        $this->assertTrue(collect($result->getData())->contains(fn ($item) => isset($item->admin_secret) && $item->admin_secret === 'XXX'));
    }

    public function testCreateRules()
    {

        $result = $this->postJson('/api/admin/trait/test-users', [
            'first_name' => 'aaa',
            'last_name' => 'aaa',
            'email' => 'aaa666@email.com',
            'description' => 'aaa',
            'admin_secret' => 'XXX'
        ]);

        $result->assertStatus(422);  // "The title field is required."

        $result = $this->postJson('/api/admin/trait/test-users', [
            'first_name' => 'aaa',
            'last_name' => 'aaa',
            'title' => 'Dr.',
            'email' => 'aaa666@email.com',
            'description' => 'aaa',
            'admin_secret' => 'XXX'
        ]);

        $result->assertOK();
    }
}
