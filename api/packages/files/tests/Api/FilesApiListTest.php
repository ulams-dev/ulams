<?php

namespace EscolaLms\Files\Tests\Api;

use EscolaLms\Core\Models\User;
use EscolaLms\Files\Enums\FilePermissionsEnum;
use EscolaLms\Files\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FilesApiListTest extends TestCase
{
    private string $url = '/api/admin/file/list';
    private string $storagePath = '/storage';

    public function testDirectoryListMainDirectory()
    {
        $file1 = UploadedFile::fake()->image('test.png');
        $file2 = UploadedFile::fake()->create('test.txt', 3, 'text/plain');

        $directory = '/';
        $path = rtrim('/storage/'.$directory, '/');
        $this->disk->putFileAs($directory, $file1, $file1->getClientOriginalName());
        $this->disk->putFileAs($directory, $file2, $file2->getClientOriginalName());

        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => $directory,
            ],
            [],
            true
        );
        $response->assertOk();
        $response->assertJsonFragment(['data' => [
            [
                'name' => $file1->getClientOriginalName(),
                'created_at' => date(DATE_RFC3339, $file1->getCTime()),
                'mime' => $file1->getMimeType(),
                'url' => $path.'/'.$file1->getClientOriginalName(),
                'isDir' => false
            ],
            [
                'name' => $file2->getClientOriginalName(),
                'created_at' => date(DATE_RFC3339, $file2->getCTime()),
                'mime' => $file2->getMimeType(),
                'url' => $path.'/'.$file2->getClientOriginalName(),
                'isDir' => false
            ],
        ]]);
    }

    public function testDirectoryListGivenDirectory()
    {
        $file1 = UploadedFile::fake()->image('test.png');
        $file2 = UploadedFile::fake()->create('test.txt', 3, 'text/plain');

        $directory = '/test';
        $path = rtrim('/storage'.$directory, '/');
        $this->disk->makeDirectory('test');
        $this->disk->putFileAs($directory, $file1, $file1->getClientOriginalName());
        $this->disk->putFileAs($directory, $file2, $file2->getClientOriginalName());

        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => $directory,
            ],
            [],
            true
        );
        $response->assertOk();
        $response->assertJsonFragment(['data' => [
            [
                'name' => $file1->getClientOriginalName(),
                'created_at' => date(DATE_RFC3339, $file1->getCTime()),
                'mime' => $file1->getMimeType(),
                'url' => $path.'/'.$file1->getClientOriginalName(),
                'isDir' => false
            ],
            [
                'name' => $file2->getClientOriginalName(),
                'created_at' => date(DATE_RFC3339, $file2->getCTime()),
                'mime' => $file2->getMimeType(),
                'url' => $path.'/'.$file2->getClientOriginalName(),
                'isDir' => false
            ],
        ]]);
    }

    public function testRecursiveListInDirectory()
    {
        $file = UploadedFile::fake()->image('test.png');
        $fileName = $file->getClientOriginalName();

        $this->disk->makeDirectory('/directory', 0777, true, true);
        $this->disk->putFileAs('/', $file, $fileName);

        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => '/',
            ],
            [],
            true
        );
        $response->assertOk();
        $response->assertJsonFragment(['data' => [
            [
                'name' => 'directory',
                'created_at' => date(DATE_RFC3339, $file->getCTime()),
                'mime' => 'directory',
                'url' => $this->storagePath.'/directory',
                'isDir' => true
            ],
            [
                'name' => $fileName,
                'created_at' => date(DATE_RFC3339, $file->getCTime()),
                'mime' => $file->getMimeType(),
                'url' => $this->storagePath.'/'.$file->getClientOriginalName(),
                'isDir' => false
            ],
        ]]);
    }

    public function testListInvalidDirectory()
    {
        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => '../../',
            ],
            [],
            true
        );
        $response->assertStatus(405);
    }

    public function testListInvalidPerPage()
    {
        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => '/',
                'perPage' => -1
            ],
            [],
            true
        );
        $response->assertStatus(302);
    }

    public function testListInvalidPage()
    {
        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => '/',
                'page' => -1,
            ],
            [],
            true
        );
        $response->assertStatus(302);
    }

    public function testFileListWithAccessToDirectories(): void
    {
        $user = User::factory()->create([
            'access_to_directories' => json_encode(['course/1']),
        ]);
        $user->givePermissionTo(FilePermissionsEnum::FILE_LIST_SELF);

        Storage::makeDirectory('course/1/topic');
        Storage::makeDirectory('course/2/topic');
        UploadedFile::fake()->image('test1.png')->storeAs('course/1', 'test1.png');
        UploadedFile::fake()->image('test2.png')->storeAs('course/2', 'test2.png');

        $this->actingAs($user, 'api')->getJson('/api/admin/file/list?' . http_build_query(['directory' => '/']))
            ->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'course',
                'mime' => 'directory',
                'isDir' => true
            ]);

        $this->actingAs($user, 'api')->getJson('/api/admin/file/list?' . http_build_query(['directory' => 'course']))
            ->assertStatus(200)
            ->assertJsonFragment([
                'name' => '1',
            ])
            ->assertJsonMissing([
                'name' => '2',
            ]);

        $this->actingAs($user, 'api')->getJson('/api/admin/file/list?' . http_build_query(['directory' => 'course/1']))
            ->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'test1.png',
            ])
            ->assertJsonFragment([
                'name' => 'topic',
            ]);
    }
}
