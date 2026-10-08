<?php

namespace EscolaLms\CsvUsers\Tests\APIs;

use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CsvUsers\Export\UsersExport;
use EscolaLms\CsvUsers\Tests\TestCase;
use EscolaLms\ModelFields\Enum\MetaFieldVisibilityEnum;
use EscolaLms\ModelFields\Facades\ModelFields;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Maatwebsite\Excel\Facades\Excel;

class ExportUsersToCsvTest extends TestCase
{
    use CreatesUsers, WithFaker, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Excel::fake();
    }

    public function testUnauthorizedAccessToExportUsersToCsv(): void
    {
        $response = $this->getJson('/api/admin/csv/users');
        $response->assertUnauthorized();
    }

    public function testAccessToExportUsersToCSV(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/csv/users');
        $response->assertOk();

        Excel::assertDownloaded('users.csv');
    }

    public function testExportUsersToCsvWithCriteria(): void
    {
        $name = $this->faker->firstName;

        $user = $this->makeStudent([
            'first_name' => $name,
        ]);

        $user2 = $this->makeStudent();

        $admin = $this->makeAdmin([
            'first_name' => $name
        ]);

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/csv/users');
        $response->assertOk();

        Excel::assertDownloaded('users.csv', function (UsersExport $export) use ($user, $user2, $admin) {
            return $export->collection()->contains('email', $user->email)
                && $export->collection()->contains('email', $user2->email)
                && $export->collection()->contains('email', $admin->email)
                && $export->collection()->contains('password', '');
        });

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/csv/users/?search=' . $user->email);
        $response->assertOk();

        Excel::assertDownloaded('users.csv', function (UsersExport $export) use ($user, $user2, $admin) {
            return $export->collection()->contains('email', $user->email)
                && !$export->collection()->contains('email', $user2->email)
                && !$export->collection()->contains('email', $admin->email);
        });

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/csv/users/?search=' . $name . '&role=' . UserRole::ADMIN);
        $response->assertOk();

        Excel::assertDownloaded('users.csv', function (UsersExport $export) use ($user, $user2, $admin) {
            return !$export->collection()->contains('email', $user->email)
                && !$export->collection()->contains('email', $user2->email)
                && $export->collection()->contains('email', $admin->email);
        });

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/csv/users/?search=' . $name. '&role=' . UserRole::STUDENT);
        $response->assertOk();

        Excel::assertDownloaded('users.csv', function (UsersExport $export) use ($user, $user2, $admin) {
            return $export->collection()->contains('email', $user->email)
                && !$export->collection()->contains('email', $user2->email)
                && !$export->collection()->contains('email', $admin->email);
        });
    }

    public function testCustomExporting(): void
    {
        $name = $this->faker->firstName;

        $user = $this->makeStudent([
            'first_name' => $name,
        ]);

        $user2 = $this->makeStudent();

        $admin = $this->makeAdmin([
            'first_name' => $name
        ]);


        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/csv/users', [
            'format' => 'not_existing_format',
        ]);

        $response->assertUnprocessable();

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/csv/users', [
            'format' => 'csv',
        ]);

        $response->assertOk();

        Excel::assertDownloaded('users.csv', function (UsersExport $export) use ($user, $user2, $admin) {
            return $export->collection()->contains('email', $user->email)
                && $export->collection()->contains('email', $user2->email)
                && $export->collection()->contains('email', $admin->email);
        });

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/csv/users', [
            'format' => 'xlsx',
        ]);

        $response->assertOk();

        Excel::assertDownloaded('users.csv', function (UsersExport $export) use ($user, $user2, $admin) {
            return $export->collection()->contains('email', $user->email)
                && $export->collection()->contains('email', $user2->email)
                && $export->collection()->contains('email', $admin->email);
        });

        $response = $this->actingAs($admin, 'api')->json('GET', '/api/admin/csv/users', [
            'format' => 'xls',
        ]);

        $response->assertOk();

        Excel::assertDownloaded('users.csv', function (UsersExport $export) use ($user, $user2, $admin) {
            return $export->collection()->contains('email', $user->email)
                && $export->collection()->contains('email', $user2->email)
                && $export->collection()->contains('email', $admin->email);
        });
    }

    public function testExportUsersToCsvWithAdditionalFields(): void
    {
        ModelFields::addOrUpdateMetadataField(
            User::class,
            'public_additional_field',
            'varchar',
            '',
            ['required', 'string', 'max:255'],
        );

        ModelFields::addOrUpdateMetadataField(
            User::class,
            'admin_additional_field',
            'varchar',
            '',
            ['required', 'string', 'max:255'],
            MetaFieldVisibilityEnum::ADMIN
        );

        $admin = $this->makeAdmin([
            'first_name' => $this->faker->firstName,
            'public_additional_field' => 'public string',
            'admin_additional_field' => 'secret string',
        ]);

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/csv/users');
        $response->assertOk();

        Excel::assertDownloaded('users.csv', function (UsersExport $export) use ($admin) {
            $this->assertTrue($export->collection()->contains('email', $admin->email));
            $this->assertTrue($export->collection()->contains('public_additional_field', 'public string'));
            $this->assertTrue($export->collection()->contains('admin_additional_field', 'secret string'));

            return true;
        });
    }
}
