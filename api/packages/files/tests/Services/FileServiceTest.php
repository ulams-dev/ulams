<?php

namespace EscolaLms\Files\Tests\Services;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Files\Http\Services\Contracts\FileServiceContract;
use EscolaLms\Files\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;

class FileServiceTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private FileServiceContract $fileService;

    public function setUp(): void
    {
        parent::setUp();

        $this->fileService = App::make(FileServiceContract::class);
    }

    public function testAddUserAccessToDirectory(): void
    {
        $user = $this->makeInstructor();
        $this->assertNull($user->access_to_directories);

        $result = $this->fileService->addUserAccessToDirectory($user, 'test/1');
        $this->assertEquals(['test/1'], $result);
        $user->refresh();
        $this->assertEquals(['test/1'], json_decode($user->access_to_directories));

        $result = $this->fileService->addUserAccessToDirectory($user, 'test/2');
        $this->assertEquals(['test/1', 'test/2'], $result);
        $user->refresh();
        $this->assertEquals(['test/1', 'test/2'], json_decode($user->access_to_directories));

        $this->fileService->addUserAccessToDirectory($user, 'test/1');
        $user->refresh();
        $this->assertEquals(['test/1', 'test/2'], json_decode($user->access_to_directories));
    }

    public function testRemoveUserAccessToDirectory(): void
    {
        $user = $this->makeInstructor([
            'access_to_directories' => json_encode(['test/1', 'test/2', 'test/3'])
        ]);

        $this->assertEquals(['test/1', 'test/2', 'test/3'], json_decode($user->access_to_directories));

        $result = $this->fileService->removeUserAccessToDirectory($user, 'test/2');
        $this->assertEquals(['test/1', 'test/3'], $result);
        $user->refresh();
        $this->assertEquals(['test/1', 'test/3'], json_decode($user->access_to_directories));
    }
}
