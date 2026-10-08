<?php

namespace EscolaLms\Cmi5\Tests\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

trait Cmi5Testing
{
    protected function getToken(): string
    {
        $user = $this->makeAdmin();
        Passport::personalAccessTokensExpireIn(now()->addMonth());
        return $user->createToken("EscolaLMS User Token")->accessToken;
    }

    protected function getCmi5UploadedFile(string $fileName): UploadedFile
    {
        $filepath = realpath(__DIR__ . '/../mocks/' . $fileName);
        $storagePath = Storage::path($fileName);

        copy($filepath, $storagePath);

        return new UploadedFile($storagePath, $fileName, 'application/zip', null, true);
    }

    protected function uploadCmi5(string $fileName, string $token = null): object {
        $file = $this->getCmi5UploadedFile($fileName);

        $request = $this;

        if (!$token) {
            $admin = $this->makeAdmin();
            $request->actingAs($admin, 'api');
        }
        else {
            $request->withHeaders([
                'Authorization' => "Bearer {$token}"
            ]);
        }

        $response = $request
            ->json('POST', '/api/admin/cmi5', ['file' => $file])
            ->assertCreated();

        return $response->getData()->data;
    }

    protected function assertJsonStructure(TestResponse $response): void
    {
        $response->assertJsonStructure([
            'data' => [[
                'id',
                'iri',
                'title',
                'au' => [[
                    'id',
                    'title',
                    'url'
                ]]
            ]]
        ]);
    }
}
