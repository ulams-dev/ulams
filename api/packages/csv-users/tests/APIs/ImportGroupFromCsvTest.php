<?php

namespace EscolaLms\CsvUsers\Tests\APIs;

use EscolaLms\Auth\Models\GroupUser;
use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CsvUsers\Enums\CsvUserPermissionsEnum;
use EscolaLms\CsvUsers\Import\UserGroupImport;
use EscolaLms\CsvUsers\Tests\Models\User;
use EscolaLms\CsvUsers\Tests\TestCase;
use EscolaLms\ModelFields\Enum\MetaFieldVisibilityEnum;
use EscolaLms\ModelFields\Facades\ModelFields;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ImportGroupFromCsvTest extends TestCase
{
    use CreatesUsers, WithFaker, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Excel::fake();
    }

    public function testUnauthorizedAccessToImportUserGroupFromCsv(): void
    {
        $response = $this->postJson('/api/admin/csv/groups');
        $response->assertUnauthorized();
    }

    public function testAccessToImportUserGroupFromCSV(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/csv/groups', [
            'file' => UploadedFile::fake()->create('group.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertOk();

        Excel::assertImported('group.csv');

        $tutor = $this->makeInstructor();
        $response = $this->actingAs($tutor, 'api')->postJson('/api/admin/csv/groups', [
            'file' => UploadedFile::fake()->create('group.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertForbidden();

        $tutor->givePermissionTo(CsvUserPermissionsEnum::CSV_USER_GROUP_IMPORT);

        $response = $this->actingAs($tutor, 'api')->postJson('/api/admin/csv/groups', [
            'file' => UploadedFile::fake()->create('group.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertOk();

        Excel::assertImported('group.csv');
    }

    public function testGroupImport(): void
    {
        GroupUser::query()->delete();

        $importData = $this->prepareImportData();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')->postJson('/api/admin/csv/groups', [
            'file' => UploadedFile::fake()->create('users.csv'),
            'return_url' => 'http://localhost/set-password',
        ]);
        $response->assertOk();

        Excel::assertImported('users.csv', function (UserGroupImport $import) use ($importData) {
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
        });

        $this->assertDatabaseHas('groups', [
            'name' => 'test group',
            'registerable' => false,
        ]);

        $this->assertDatabaseCount('group_user', 5);

    }

    private function prepareImportData(): Collection
    {
        $admin = $this->makeAdmin();

        $adminData = collect([
            'email' => $admin->email,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'roles' => json_encode($admin->roles->pluck('name')),
            'group_name' => 'test group',
            'group_registerable' => false,
        ]);

        $studentData = collect([
            'email' => $this->faker->email,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'roles' => json_encode([UserRole::STUDENT, UserRole::TUTOR]),
            'permissions' => json_encode([CsvUserPermissionsEnum::CSV_USERS_IMPORT]),
            'group_name' => 'test group',
            'group_registerable' => false,
        ]);

        $importData = collect([$adminData, $studentData]);

        for ($i = 0; $i < 3; $i++) {
            $importData->push(collect([
                'email' => $this->faker->email,
                'first_name' => $this->faker->firstName,
                'last_name' => $this->faker->lastName,
                'created_at' => '',
                'group_name' => 'test group',
                'group_registerable' => false,
            ]));
        }

        return $importData;
    }
}
