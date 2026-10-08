<?php

namespace EscolaLms\CsvUsers\Tests\APIs;

use EscolaLms\Auth\Models\Group;
use EscolaLms\Auth\Models\User as AuthUser;
use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CsvUsers\Enums\CsvUserPermissionsEnum;
use EscolaLms\CsvUsers\Events\EscolaLmsImportedNewUserTemplateEvent;
use EscolaLms\CsvUsers\Import\UsersImport;
use EscolaLms\CsvUsers\Tests\Models\User;
use EscolaLms\CsvUsers\Tests\TestCase;
use EscolaLms\ModelFields\Enum\MetaFieldVisibilityEnum;
use EscolaLms\ModelFields\Facades\ModelFields;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ImportUsersFromCsvTest extends TestCase
{
    use CreatesUsers, WithFaker, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Excel::fake();
    }

    public function testUnauthorizedAccessToImportUsersFromCsv(): void
    {
        $response = $this->postJson('/api/admin/csv/users');
        $response->assertUnauthorized();
    }

    public function testAccessToImportUsersFromCSV(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/csv/users', [
            'file' => UploadedFile::fake()->create('users.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertOk();

        Excel::assertImported('users.csv');

        $tutor = $this->makeInstructor();
        $response = $this->actingAs($tutor, 'api')->postJson('/api/admin/csv/users', [
            'file' => UploadedFile::fake()->create('users.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertForbidden();

        $tutor->givePermissionTo(CsvUserPermissionsEnum::CSV_USERS_IMPORT);

        $response = $this->actingAs($tutor, 'api')->postJson('/api/admin/csv/users', [
            'file' => UploadedFile::fake()->create('users.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);

        $response->assertOk();

        Excel::assertImported('users.csv');
    }

    public function fileFormatProvider(): array
    {
        return [
            'csv' => ['users.csv'],
            'xlsx' => ['users.xlsx'],
            'xls' => ['users.xls'],
        ];
    }

    /**
     * @dataProvider fileFormatProvider
     */
    public function testUsersImport(string $value): void
    {
        $importData = $this->prepareImportData();

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/csv/users', [
            'file' => UploadedFile::fake()->create($value),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertOk();

        Excel::assertImported($value, function (UsersImport $import) use ($importData) {
            $import->collection($importData);
            return true;
        });

        $importData->each(function ($user) {

            $this->assertDatabaseHas('users', [
                'email' => $user->get('email'),
                'first_name' => $user->get('first_name'),
                'last_name' => $user->get('last_name'),
            ]);

            $dbUser = User::where('email', $user->get('email'))->firstOrFail();
            $this->assertNotNull($dbUser->created_at);
            $this->assertEqualsCanonicalizing($dbUser->roles->pluck('name')->toArray(), $user->get('roles'));
            $this->assertEqualsCanonicalizing($dbUser->permissions->pluck('name')->toArray(), $user->get('permissions'));
            $this->assertEqualsCanonicalizing($dbUser->groups->pluck('name')->toArray(), $user->get('groups'));
        });

        $this->assertDatabaseHas('users', [
           'email' => 'test_user1@test.test',
           'password' => null,
        ]);

        $this->assertDatabaseHas('users', [
           'email' => 'test_user2@test.test',
           'password' => null,
        ]);

        $user = User::query()->where('email', 'test_user3@test.test')->first();
        Hash::check('password', $user->password);
        $this->assertDatabaseCount('groups', 3);
    }

    public function testUsersImportValidation(): void
    {
        $invalidImportData = collect();

        for ($i = 0; $i < $this->faker->numberBetween(1, 5); $i++) {
            $invalidImportData->push(collect([
                'test' => $this->faker->text(15),
            ]));
        }

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/csv/users', [
            'file' => UploadedFile::fake()->create('users.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertOk();

        Excel::assertImported('users.csv', function (UsersImport $import) use ($invalidImportData) {
            $validator = Validator::make($invalidImportData->toArray(), $import->rules());
            $this->assertTrue($validator->fails());
            return true;
        });
    }

    public function testDispatchNewUserImportedTemplateEvent(): void
    {
        Event::fake();
        Notification::fake();

        $userToImport = collect([
            'email' => $this->faker->email,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
        ]);

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/csv/users', [
            'file' => UploadedFile::fake()->create('users.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertOk();

        Excel::assertImported('users.csv', function (UsersImport $import) use ($userToImport) {
            $import->collection(collect([$userToImport]));
            return true;
        });

        Event::assertDispatched(EscolaLmsImportedNewUserTemplateEvent::class,
            function (EscolaLmsImportedNewUserTemplateEvent $event) use ($userToImport) {
                $eventUser = $event->getUser();
                $this->assertEquals($userToImport['email'], $eventUser->email);
                $this->assertTrue($eventUser->is_active);
                $this->assertTrue($eventUser->hasVerifiedEmail());
                $this->assertEquals('http://localhost/set-password', $event->getReturnUrl());

                return true;
            });
    }

    public function testUserImportWithAdditionalFields(): void
    {
        ModelFields::addOrUpdateMetadataField(
            AuthUser::class,
            'public_additional_field',
            'varchar',
            '',
        );

        ModelFields::addOrUpdateMetadataField(
            AuthUser::class,
            'boolean_additional_field',
            'boolean',
            '',
        );

        ModelFields::addOrUpdateMetadataField(
            AuthUser::class,
            'admin_additional_field',
            'varchar',
            '',
            ['string', 'max:255'],
            MetaFieldVisibilityEnum::ADMIN
        );

        $email = $this->faker->email;
        $studentData = collect([
            'email' => $email,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'public_additional_field' => 'public string',
            'admin_additional_field' => 'secret string',
            'boolean_additional_field' => false,
        ]);

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/csv/users', [
            'file' => UploadedFile::fake()->create('users.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertOk();

        Excel::assertImported('users.csv', function (UsersImport $import) use ($studentData) {
            $import->collection(collect([$studentData]));

            return true;
        });

        $user = User::where('email', $email)->first();
        $this->assertEquals('public string', $user->public_additional_field);
        $this->assertEquals('secret string', $user->admin_additional_field);
        $this->assertFalse($user->boolean_additional_field);
    }

    private function prepareImportData(): Collection
    {
        $admin = $this->makeAdmin();
        $existingGroup = Group::factory()->state(['name' => 'existing name'])->create();

        $adminData = collect([
            'email' => $admin->email,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'roles' => json_encode($admin->roles->pluck('name')),
            'groups' => json_encode(['new group 1']),
        ]);

        $studentData = collect([
            'email' => 'test_user1@test.test',
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'roles' => json_encode([UserRole::STUDENT, UserRole::TUTOR]),
            'permissions' => json_encode([CsvUserPermissionsEnum::CSV_USERS_IMPORT]),
            'groups' => json_encode(['new group 1']),
        ]);

        $studentData2 = collect([
            'email' => 'test_user2@test.test',
            'password' => '',
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'roles' => json_encode([UserRole::STUDENT, UserRole::TUTOR]),
            'permissions' => json_encode([CsvUserPermissionsEnum::CSV_USERS_IMPORT]),
            'groups' => json_encode(['new group 2']),
        ]);

        $studentData3 = collect([
            'email' => 'test_user3@test.test',
            'password' => 'password',
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'roles' => json_encode([UserRole::STUDENT, UserRole::TUTOR]),
            'permissions' => json_encode([CsvUserPermissionsEnum::CSV_USERS_IMPORT]),
            'groups' => json_encode([$existingGroup->name]),
        ]);

        $importData = collect([$adminData, $studentData, $studentData2, $studentData3]);

        for ($i = 0; $i < $this->faker->numberBetween(1, 10); $i++) {
            $importData->push(collect([
                'email' => $this->faker->email,
                'first_name' => $this->faker->firstName,
                'last_name' => $this->faker->lastName,
                'created_at' => '',
            ]));
        }

        return $importData;
    }
}
