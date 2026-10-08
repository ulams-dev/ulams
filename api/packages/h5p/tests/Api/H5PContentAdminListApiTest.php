<?php

namespace Ulams\H5P\Tests\Api;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\H5P\Database\Factories\H5PContentFactory;
use Ulams\H5P\Database\Seeders\H5PPermissionSeeder;
use Ulams\H5P\Enums\H5PPermissionsEnum;
use Ulams\H5P\Tests\TestCase;

class H5PContentAdminListApiTest extends TestCase
{
    use CreatesUsers;

    private const URI = '/api/admin/h5p/contents';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(H5PPermissionSeeder::class);
    }

    private function useInTopics(int $contentId, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            DB::table('topic_h5ps')->insert(['value' => $contentId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function item(array $data, int $id): ?array
    {
        return collect($data)->firstWhere('id', $id);
    }

    public function testGuestIsUnauthorized(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    public function testStudentIsForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')->getJson(self::URI)->assertForbidden();
    }

    public function testAdminListsContentsWithTopicCount(): void
    {
        $admin = $this->makeAdmin();
        $used = H5PContentFactory::create(['title' => 'Used quiz', 'user_id' => $admin->getKey()]);
        $unused = H5PContentFactory::create(['title' => 'Unused quiz', 'main_library' => 'H5P.TrueFalse', 'library_version' => '1.8']);
        $this->useInTopics($used->getKey(), 2);

        $response = $this->actingAs($admin, 'api')
            ->getJson(self::URI . '?per_page=0')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [['id', 'title', 'library', 'main_library', 'library_version', 'user_id', 'created_at', 'updated_at', 'count_h5p']],
            ]);

        $data = $response->json('data');
        $this->assertEquals([
            'id' => $used->getKey(),
            'title' => 'Used quiz',
            'library' => 'H5P.MultiChoice 1.16',
            'main_library' => 'H5P.MultiChoice',
            'library_version' => '1.16',
            'user_id' => $admin->getKey(),
            'count_h5p' => 2,
        ], collect($this->item($data, $used->getKey()))->except(['created_at', 'updated_at'])->all());
        $this->assertSame(0, $this->item($data, $unused->getKey())['count_h5p']);
        $this->assertSame('H5P.TrueFalse 1.8', $this->item($data, $unused->getKey())['library']);
    }

    public function testPaginationMetaAndOrdering(): void
    {
        $admin = $this->makeAdmin();
        $first = H5PContentFactory::create();
        $second = H5PContentFactory::create();
        $this->useInTopics($first->getKey(), 3);

        $this->actingAs($admin, 'api')
            ->getJson(self::URI . '?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.id', $second->getKey());

        $this->actingAs($admin, 'api')
            ->getJson(self::URI . '?per_page=1&order_by=count_h5p&order=DESC')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->getKey())
            ->assertJsonPath('data.0.count_h5p', 3);

        $this->actingAs($admin, 'api')
            ->getJson(self::URI . '?order_by=unknown')
            ->assertUnprocessable();
    }

    public function testTitleFilter(): void
    {
        $admin = $this->makeAdmin();
        $match = H5PContentFactory::create(['title' => 'Photosynthesis 100% basics']);
        H5PContentFactory::create(['title' => 'Something else']);

        $data = $this->actingAs($admin, 'api')
            ->getJson(self::URI . '?per_page=0&title=' . urlencode('synthesis 100%'))
            ->assertOk()
            ->json('data');

        $this->assertSame([$match->getKey()], array_column($data, 'id'));
    }

    public function testAuthorListSeesOnlyOwnContents(): void
    {
        $author = $this->makeInstructor();
        $other = $this->makeInstructor();
        Permission::findOrCreate(H5PPermissionsEnum::H5P_AUTHOR_LIST, 'api');
        $author->givePermissionTo(H5PPermissionsEnum::H5P_AUTHOR_LIST);

        $own = H5PContentFactory::create(['user_id' => $author->getKey()]);
        H5PContentFactory::create(['user_id' => $other->getKey()]);

        $data = $this->actingAs($author, 'api')
            ->getJson(self::URI . '?per_page=0&author_id=' . $other->getKey())
            ->assertOk()
            ->json('data');

        $this->assertSame([$own->getKey()], array_column($data, 'id'));
    }

    public function testAdminCanFilterByAuthor(): void
    {
        $admin = $this->makeAdmin();
        $author = $this->makeInstructor();
        $own = H5PContentFactory::create(['user_id' => $author->getKey()]);
        H5PContentFactory::create(['user_id' => $admin->getKey()]);

        $data = $this->actingAs($admin, 'api')
            ->getJson(self::URI . '?per_page=0&author_id=' . $author->getKey())
            ->assertOk()
            ->json('data');

        $this->assertSame([$own->getKey()], array_column($data, 'id'));
    }
}
