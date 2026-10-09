<?php

namespace Tests\Feature;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Scorm\Tests\ScormTestTrait;
use Ulams\Uploads\Tests\ZipFixtures;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Ulams\Scorm\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Peopleaps\Scorm\Entity\Scorm;
use Peopleaps\Scorm\Model\ScormModel;
use Peopleaps\Scorm\Model\ScormScoModel;

class ScormAdminApiTest extends TestCase
{
    use DatabaseTransactions, ScormTestTrait, WithFaker, CreatesUsers, ZipFixtures;

    public function test_content_upload(): void
    {
        $response = $this->uploadScorm();
        $data = $response->getData();

        $response->assertStatus(200);
        $this->assertEquals($data->data->scormData->scos[0]->title, "Employee Health and Wellness (Sample Course)");
        $this->assertEquals($this->user->getKey(), $data->data->model->user_id);
    }

    public function test_content_upload_invalid_data(): void
    {
        // a .zip that is not a zip: the upload guard sniffs the content (finfo)
        $response = $this->actingAs($this->user, 'api')
            ->json('POST', '/api/admin/scorm/upload', [
                'zip' => UploadedFile::fake()->create('file.zip', 100, 'application/zip'),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['zip' => 'not allowed for scorm uploads']);
    }

    public function test_content_upload_zip_without_manifest(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->json('POST', '/api/admin/scorm/upload', [
                'zip' => new UploadedFile($this->makeZip(['index.html' => 'x']), 'file.zip', null, null, true),
            ]);

        $response->assertUnprocessable();
        $response->assertJson([
            'success' => false,
            'message' => 'invalid_scorm_archive_message'
        ]);
    }

    public static function hostilePackages(): array
    {
        return [
            'zip-slip' => [['../../../../var/www/html/public/evil.php' => '<?php echo 1;'], [], 'leaves its folder'],
            'absolute path' => [['/tmp/evil.html' => 'x'], [], 'absolute path'],
            'symlink' => [[], ['leak.txt' => '/var/www/html/.env'], 'symbolic link'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hostilePackages')]
    public function test_hostile_package_is_rejected_and_nothing_is_stored(array $extra, array $symlinks, string $message): void
    {
        Storage::fake(config('scorm.disk'));
        $zip = $this->makeScormZip($extra, $symlinks);

        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/upload', [
            'zip' => new UploadedFile($zip, 'course.zip', null, null, true),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['zip' => $message]);
        $this->assertSame([], Storage::disk(config('scorm.disk'))->allFiles());
        $this->assertSame(0, ScormModel::query()->count());
    }

    public function test_zip_bomb_is_rejected(): void
    {
        config(['ulams_uploads.zip.package.max_ratio' => 100]);
        $zip = $this->makeScormZip(['assets/zeros.bin' => str_repeat("\0", 8 * 1024 * 1024)]);

        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/upload', [
            'zip' => new UploadedFile($zip, 'course.zip', null, null, true),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['zip' => 'compression ratio']);
    }

    public function test_service_rejects_zip_slip_without_request_validation(): void
    {
        // course imports call the service directly
        Storage::fake(config('scorm.disk'));
        $zip = $this->makeScormZip(['../escape.html' => 'x']);

        try {
            app(\Ulams\Scorm\Services\Contracts\ScormServiceContract::class)
                ->uploadScormArchive(new UploadedFile($zip, 'course.zip', null, null, true));
            $this->fail('Expected the package to be rejected.');
        } catch (\Ulams\Uploads\Exceptions\UploadRejected $e) {
            $this->assertSame('zip_slip', $e->reason);
        }
        $this->assertSame([], Storage::disk(config('scorm.disk'))->allFiles());
    }

    public function test_valid_generated_package_is_extracted_under_its_folder(): void
    {
        Storage::fake(config('scorm.disk'));

        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/upload', [
            'zip' => new UploadedFile($this->makeScormZip(['js/./app.js' => 'run()']), 'course.zip', null, null, true),
        ]);

        $response->assertOk();
        $hash = $response->json('data.scormData.hashName');
        Storage::disk(config('scorm.disk'))->assertExists("scorm/scorm_12/{$hash}/index.html");
        Storage::disk(config('scorm.disk'))->assertExists("scorm/scorm_12/{$hash}/js/app.js");
    }

    public function test_content_upload_invalid_data_format(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->json('POST', '/api/admin/scorm/upload', [
                'zip' => UploadedFile::fake()->create('file.svg', 100, 'application/svg'),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['zip' => 'must be a file of type: zip.']);
    }

    public function test_content_parse(): void
    {
        $zipFile = $this->getUploadScormFile();
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/parse', [
            'zip' => $zipFile,
        ]);

        $data = $response->getData();

        $response->assertOk();
        $this->assertEquals($data->data->scos[0]->title, "Employee Health and Wellness (Sample Course)");
    }

    public function test_delete_scorm(): void
    {
        $response = $this->uploadScorm();
        $data = $response->getData();
        $scormData = $data->data->scormData;
        $model = $data->data->model;
        $path = 'scorm' . DIRECTORY_SEPARATOR . $scormData->version . DIRECTORY_SEPARATOR . $scormData->hashName;

        $response = $this->actingAs($this->user, 'api')->json('DELETE', '/api/admin/scorm/' . $model->id);

        $response->assertOk();
        $this->assertFalse(Storage::disk(config('scorm.disk'))->exists($path));
        $this->assertDatabaseMissing('scorm', [
            'id' => $model->id,
            'uuid' => $model->uuid,
        ]);
        $this->assertDatabaseMissing('scorm_sco', [
            'uuid' => $scormData->scos[0]->uuid,
        ]);
    }

    public function test_delete_owned_scorm(): void
    {
        $tutor = $this->makeInstructor();
        $scorm = $this->createScorm();

        $this->actingAs($tutor, 'api')
            ->deleteJson('/api/admin/scorm/' . $scorm->getKey())
            ->assertForbidden();

        $scorm->user_id = $tutor->getKey();
        $scorm->save();

        $this->actingAs($tutor, 'api')
            ->deleteJson('/api/admin/scorm/' . $scorm->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('scorm', [
            'id' => $scorm->id,
        ]);
    }

    public function test_get_model_list_paginated(): void
    {
        $this->createManyScorm(10);

        $this->actingAs($this->user, 'api')->get('/api/admin/scorm?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data.data');
    }

    public function test_get_owned_scorm_list_paginated(): void
    {
        $this->createManyScorm(10);
        $owner = $this->makeInstructor();
        $this->createManyScorm(5)->each(function (ScormModel $scorm) use ($owner) {
            $scorm->user_id = $owner->getKey();
            $scorm->save();
        });

        $this->actingAs($owner, 'api')->get('/api/admin/scorm?per_page=15')
            ->assertOk()
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.total', 5);
    }

    public function test_get_model_list_unpaginated(): void
    {
        $this->createManyScorm(30);

        $this->actingAs($this->user, 'api')->get('/api/admin/scorm?per_page=0')
            ->assertOk()
            ->assertJsonCount(30, 'data.data');
    }

    public function test_get_model_list_with_sorts(): void
    {
        $scormOne = $this->createScorm();
        $scormOne->version = Scorm::SCORM_12;
        $scormOne->save();

        $scormTwo = $this->createScorm();
        $scormTwo->version = Scorm::SCORM_2004;
        $scormTwo->save();


        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/scorm', [
            'order_by' => 'version',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->getData()->data->data[0]->version === Scorm::SCORM_12);
        $this->assertTrue($response->getData()->data->data[1]->version === Scorm::SCORM_2004);

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/scorm', [
            'order_by' => 'version',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->getData()->data->data[0]->version === Scorm::SCORM_2004);
        $this->assertTrue($response->getData()->data->data[1]->version === Scorm::SCORM_12);

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/scorm', [
            'order_by' => 'id',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->getData()->data->data[0]->id === $scormOne->getKey());
        $this->assertTrue($response->getData()->data->data[1]->id === $scormTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/scorm', [
            'order_by' => 'id',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->getData()->data->data[0]->id === $scormTwo->getKey());
        $this->assertTrue($response->getData()->data->data[1]->id === $scormOne->getKey());

    }

    public function test_search_model_list(): void
    {
        $scormSco = $this->createManyScos(10)[3];

        $response = $this->actingAs($this->user, 'api')->get('/api/admin/scorm?per_page=5&search=' . $scormSco->title)
            ->assertOk()
            ->assertJsonCount(1, 'data.data.0.scos')
            ->assertJsonCount(1, 'data.data');

        $data = $response->getData()->data->data;
        $this->assertEquals($data[0]->scos[0]->title, $scormSco->title);
        $this->assertEquals($data[0]->scos[0]->uuid, $scormSco->uuid);
    }

    public function test_get_model_list(): void
    {
        $response = $this->uploadScorm();
        $data = $response->getData();

        $response = $this->actingAs($this->user, 'api')->get('/api/admin/scorm');
        $list = $response->getData();

        $found = array_filter($list->data->data, function ($item) use ($data) {
            if ($item->uuid === $data->data->model->uuid) {
                return true;
            }
            return false;
        });

        $this->assertCount(1, $found);
    }

    public function test_get_scos_list()
    {
        $scormSco = new ScormScoModel;
        $scormSco->uuid = $this->faker->uuid;
        $scormSco->save();

        $scormSco = new ScormScoModel;
        $scormSco->uuid = $this->faker->uuid;
        $scormSco->save();

        $this->actingAs($this->user, 'api')->get('/api/admin/scorm/scos')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'scorm_id',
                    'uuid',
                    'entry_url',
                    'identifier',
                    'title',
                    'sco_parameters',
                ]]
            ]);

    }

    public function test_player_view(): void
    {
        $response = $this->uploadScorm();
        $data = $response->getData();

        $response = $this->actingAs($this->user, 'api')->get('/api/scorm/play/' . $data->data->scormData->scos[0]->uuid);
        $response->assertOk();
    }

    public function test_adapt_packages_are_detected_and_labelled(): void
    {
        Storage::fake(config('scorm.disk'));
        // a minimal adapt-contrib-spoor export: SCORM manifest plus the Adapt runtime layout
        $adapt = $this->makeScormZip([
            'adapt/js/adapt.min.js' => 'window.Adapt = {};',
            'course/config.json' => '{"_spoor":{"_isEnabled":true}}',
            'course/en/course.json' => '{"title":"Adapt fixture"}',
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson('/api/admin/scorm/upload', [
            'zip' => new UploadedFile($adapt, 'adapt.zip', null, null, true),
        ])->assertOk();

        $this->assertSame('adapt', $response->json('data.model.source_format'));
        $this->assertDatabaseHas('scorm', ['id' => $response->json('data.model.id'), 'source_format' => 'adapt']);
        $this->actingAs($this->user, 'api')->getJson('/api/admin/scorm?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['source_format' => 'adapt']);

        $plain = $this->actingAs($this->user, 'api')->postJson('/api/admin/scorm/upload', [
            'zip' => new UploadedFile($this->makeScormZip(), 'plain.zip', null, null, true),
        ])->assertOk();
        $this->assertNull($plain->json('data.model.source_format'));
    }
}
