<?php

namespace EscolaLms\Files\Http\Requests;

use EscolaLms\Core\Models\User;
use EscolaLms\Files\Enums\FilePermissionsEnum;
use EscolaLms\Files\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class FilesApiFindByNameTest extends TestCase
{
    private string $url = '/api/admin/file/find';

    /**
     * @test
     */
    public function testFindFilesByNameEquals()
    {
        $file = UploadedFile::fake()->create('test-name-equals.txt', 3, 'text/plain');

        $directory1 = '/';
        $path1 = rtrim('/storage/'.$directory1, '/');
        $this->disk->putFileAs($directory1, $file, $file->getClientOriginalName());

        $directory2 = '/directory2';
        $path2 = rtrim('/storage'.$directory2, '/');
        $this->disk->putFileAs($directory2, $file, $file->getClientOriginalName());


        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => '/',
                'name' => 'test'
            ],
            [],
            true
        );
        $response->assertOk();

        $response->assertJsonFragment(['data' => [
            [
                'name' => $file->getClientOriginalName(),
                'created_at' => date(DATE_RFC3339, $file->getCTime()),
                'mime' => $file->getMimeType(),
                'url' => $path1.'/'.$file->getClientOriginalName(),
                'isDir' => false
            ],
            [
                'name' => $file->getClientOriginalName(),
                'created_at' => date(DATE_RFC3339, $file->getCTime()),
                'mime' => $file->getMimeType(),
                'url' => $path2.'/'.$file->getClientOriginalName(),
                'isDir' => false
            ]
        ]]);
    }

    /**
     * @test
     */
    public function testFindFilesByNameContains()
    {
        $filename = 'test-name-contains.txt';
        $file = UploadedFile::fake()->create($filename, 3, 'text/plain');

        $directory1 = '/c/directoryC1';
        $path1 = rtrim('/storage'.$directory1, '/');
        $this->disk->putFileAs($directory1, $file, $file->getClientOriginalName());

        $directory2 = '/c/directoryC2/directory';
        $path2 = rtrim('/storage'.$directory2, '/');
        $this->disk->putFileAs($directory2, $file, $file->getClientOriginalName());


        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => '/c',
                'name' => 'tes'
            ],
            [],
            true
        );
        $response->assertOk();

        $response->assertJsonFragment(['data' => [
            [
                'name' => $filename,
                'created_at' => date(DATE_RFC3339, $file->getCTime()),
                'mime' => $file->getMimeType(),
                'url' => $path1.'/'.$file->getClientOriginalName(),
                'isDir' => false
            ],
            [
                'name' => $filename,
                'created_at' => date(DATE_RFC3339, $file->getCTime()),
                'mime' => $file->getMimeType(),
                'url' => $path2.'/'.$file->getClientOriginalName(),
                'isDir' => false
            ]
        ]]);
    }

    public function testListInvalidDirectory()
    {
        $response = $this->getWithQuery(
            $this->url,
            [
                'directory' => '../../',
                'name' =>'name'
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
                'name' =>'name',
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
                'name' =>'name',
                'page' => -1,
            ],
            [],
            true
        );
        $response->assertStatus(302);
    }

    public function testFindByNameWithAccessToDirectories(): void
    {
        $user = User::factory()->create([
            'access_to_directories' => json_encode(['course/1']),
        ]);
        $user->givePermissionTo(FilePermissionsEnum::FILE_LIST_SELF);

        $path1 = UploadedFile::fake()->image('test.png')->storeAs('course/1', 'test.png');
        $path2 =UploadedFile::fake()->image('test.png')->storeAs('course/2', 'test.png');

        $this->actingAs($user, 'api')->getJson('/api/admin/file/find?' . http_build_query(['directory' => '/', 'name'=>'test.png']))
            ->assertStatus(200)
            ->assertJsonFragment([
                'url' => Storage::url($path1)
            ])
            ->assertJsonMissing([
                'url' => Storage::url($path2)
            ]);
    }

}
