<?php

namespace EscolaLms\Files\Tests\Api;

use EscolaLms\Files\Tests\TestCase;
use Illuminate\Http\UploadedFile;

class FilesApiMoveTest extends TestCase
{
    private $url = '/api/admin/file/move';

    public function testMoveExistingFileInRoot()
    {
        $source = UploadedFile::fake()->image('test.png');
        $this->disk->putFileAs('/', $source, $source->getClientOriginalName());

        $sourceUrl = $source->getClientOriginalName();
        $destinationUrl = '/storage/test2.png';

        $response = $this->actingAs(auth()->user(), 'api')->postJson($this->url,['source_url'=>$sourceUrl, 'destination_url'=>$destinationUrl]);
        $response->assertOk();
    }

    public function testMoveExistingFileFromRoot()
    {
        $source = UploadedFile::fake()->image('test.png');
        $this->disk->putFileAs('/', $source, $source->getClientOriginalName());

        $sourceUrl = $source->getClientOriginalName();
        $destinationUrl = '/storage/subdirectory/test2.png';

        $response = $this->actingAs(auth()->user(), 'api')->postJson($this->url,['source_url'=>$sourceUrl, 'destination_url'=>$destinationUrl]);
        $response->assertOk();
    }

    public function testMoveExistingFileToRoot()
    {
        $source = UploadedFile::fake()->image('test.png');
        $this->disk->putFileAs('/subdirectory', $source, $source->getClientOriginalName());

        $sourceUrl = 'subdirectory/'.$source->getClientOriginalName();
        $destinationUrl = '/test2.png';

        $response = $this->actingAs(auth()->user(), 'api')->postJson($this->url,['source_url'=>$sourceUrl, 'destination_url'=>$destinationUrl]);
        $response->assertOk();
    }

    public function testMoveMissingFile()
    {
        $sourceUrl = '/test1.png';
        $destinationUrl = '/test2.png';

        $response = $this->actingAs(auth()->user(), 'api')->postJson($this->url,['source_url'=>$sourceUrl, 'destination_url'=>$destinationUrl]);
        $response->assertStatus(422);
    }

    public function testMoveFileWithMissingSource()
    {
        $response = $this->actingAs(auth()->user(), 'api')->postJson($this->url,['destination_url'=>'/test2.jpg']);
        $response->assertStatus(422);
    }

    public function testMoveFileWithMissingDestination()
    {
        $source = UploadedFile::fake()->image('test.png');
        $this->disk->putFileAs('/', $source, $source->getClientOriginalName());

        $response = $this->actingAs(auth()->user(), 'api')->postJson($this->url,['source_url'=>'/storage/'.$source->getClientOriginalName()]);
        $response->assertStatus(422);
    }
}
