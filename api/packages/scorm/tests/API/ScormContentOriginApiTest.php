<?php

namespace Ulams\Scorm\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Peopleaps\Scorm\Model\ScormScoModel;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Scorm\Services\TrackingToken;
use Ulams\Scorm\Tests\ScormTestTrait;
use Ulams\Scorm\Tests\TestCase;

class ScormContentOriginApiTest extends TestCase
{
    use DatabaseTransactions, ScormTestTrait, CreatesUsers;

    private string $scoUuid;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('scorm.disk'));
        $this->scoUuid = $this->uploadScorm('RuntimeBasicCalls_SCORM12.zip')->json('data.scormData.scos.0.uuid');
        config(['scorm.content_origin' => 'http://coffee.content.localhost']);
    }

    public function test_launch_returns_the_player_on_the_content_origin_and_publishes_it(): void
    {
        $student = $this->makeStudent();

        $response = $this->actingAs($student, 'api')->postJson("/api/scorm/launch/{$this->scoUuid}");

        $response->assertOk();
        $url = $response->json('data.url');
        $this->assertStringStartsWith('http://coffee.content.localhost/scorm/_player/player.html#', $url);
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->assertSame($this->scoUuid, $fragment['sco']);
        $this->assertSame('http://localhost', $fragment['api']);
        $this->assertSame($student->getKey(), TrackingToken::verify($fragment['token'], $this->scoUuid));

        foreach (['player.html', 'player.js', 'scorm-again.min.js'] as $file) {
            Storage::disk(config('scorm.disk'))->assertExists("scorm/_player/{$file}");
        }
        $this->assertStringNotContainsString('cdn.jsdelivr.net', Storage::disk(config('scorm.disk'))->get('scorm/_player/player.html'));
    }

    public function test_launch_without_a_content_origin_falls_back_to_the_legacy_player(): void
    {
        config(['scorm.content_origin' => null, 'ulams_uploads.content_origin' => null]);

        $this->actingAs($this->makeStudent(), 'api')
            ->postJson("/api/scorm/launch/{$this->scoUuid}")
            ->assertOk()
            ->assertJsonPath('data.url', null);
    }

    public function test_launch_requires_a_signed_in_user_and_a_known_sco(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/scorm/launch/{$this->scoUuid}")->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/scorm/launch/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_the_tracking_token_reads_launch_data_and_stores_tracking(): void
    {
        $student = $this->makeStudent();
        $token = $this->launchToken($student);

        $this->withTracking($token)->getJson("/api/scorm/content/{$this->scoUuid}")
            ->assertOk()
            ->assertJsonPath('data.version', 'scorm_12')
            ->assertJsonPath('data.uuid', $this->scoUuid)
            ->assertJsonPath('data.entry_url', fn ($url) => str_starts_with($url, '/scorm/scorm_12/'));

        $this->withTracking($token)->postJson("/api/scorm/content/{$this->scoUuid}/track", [
            'cmi' => ['cmi.core.lesson_status' => 'passed', 'cmi.core.lesson_location' => '3'],
        ])->assertOk();

        $this->assertDatabaseHas('scorm_sco_tracking', [
            'sco_id' => ScormScoModel::where('uuid', $this->scoUuid)->value('id'),
            'user_id' => $student->getKey(),
            'lesson_status' => 'passed',
            'lesson_location' => '3',
        ]);
    }

    public function test_the_passport_token_and_foreign_or_forged_tracking_tokens_are_rejected(): void
    {
        $student = $this->makeStudent();
        $token = $this->launchToken($student);
        $other = $this->uploadScorm('RuntimeBasicCalls_SCORM20043rdEdition.zip')->json('data.scormData.scos.0.uuid');

        // the learner's own API token is not a tracking token, in either header
        $passport = $student->createToken('test')->accessToken;
        $this->withTracking($passport)->getJson("/api/scorm/content/{$this->scoUuid}")->assertUnauthorized();
        $this->withHeaders(['X-Ulams-Tracking-Token' => ''])->withToken($passport)->getJson("/api/scorm/content/{$this->scoUuid}")->assertUnauthorized();
        $this->withToken($token)->withHeaders(['X-Ulams-Tracking-Token' => ''])->getJson("/api/scorm/content/{$this->scoUuid}")->assertUnauthorized();
        // a token is bound to its SCO
        $this->withTracking($token)->getJson("/api/scorm/content/{$other}")->assertUnauthorized();
        $this->withTracking($token)->postJson("/api/scorm/content/{$other}/track", ['cmi' => []])->assertUnauthorized();
        // tampered payload (another user id) keeps the old signature
        [$payload, $signature] = explode('.', $token);
        $forged = rtrim(strtr(base64_encode(json_encode(['u' => 1, 's' => $this->scoUuid, 'exp' => time() + 60])), '+/', '-_'), '=');
        $this->withTracking($forged . '.' . $signature)->getJson("/api/scorm/content/{$this->scoUuid}")->assertUnauthorized();
        // no token
        $this->withHeaders(['X-Ulams-Tracking-Token' => ''])->getJson("/api/scorm/content/{$this->scoUuid}")->assertUnauthorized();
    }

    public function test_expired_tracking_tokens_are_rejected(): void
    {
        $token = TrackingToken::issue($this->makeStudent()->getKey(), $this->scoUuid, -1);

        $this->withTracking($token)->getJson("/api/scorm/content/{$this->scoUuid}")->assertUnauthorized();
    }

    public function test_tenant_isolation_tokens_of_one_tenant_are_rejected_by_another(): void
    {
        // Tenants are separate databases with their own APP_KEY (ADR 0007); the tracking token is
        // signed with a key derived from it. Same SCO uuid, same user id, other tenant: rejected.
        $token = $this->launchToken($this->makeStudent());

        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        $this->withTracking($token)->getJson("/api/scorm/content/{$this->scoUuid}")->assertUnauthorized();
        $this->withTracking($token)->postJson("/api/scorm/content/{$this->scoUuid}/track", ['cmi' => []])->assertUnauthorized();
    }

    public function test_tenant_isolation_launch_uses_the_tenants_own_content_origin_and_api_host(): void
    {
        $student = $this->makeStudent();
        config(['scorm.content_origin' => 'http://tea.content.localhost']);

        $url = $this->actingAs($student, 'api')
            ->postJson("http://tea.localhost/api/scorm/launch/{$this->scoUuid}")
            ->json('data.url');

        $this->assertStringStartsWith('http://tea.content.localhost/', $url);
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->assertSame('http://tea.localhost', $fragment['api']);
    }

    public function test_vendored_scorm_again_replaces_the_cdn(): void
    {
        $this->get('/api/scorm/assets/scorm-again.min.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        config(['scorm.content_origin' => null, 'ulams_uploads.content_origin' => null]);
        $this->actingAs($this->user, 'api')->get("/api/scorm/play/{$this->scoUuid}")
            ->assertOk()
            ->assertDontSee('cdn.jsdelivr.net')
            ->assertSee('/api/scorm/assets/scorm-again.min.js');
    }

    private function withTracking(string $token): static
    {
        return $this->withHeaders(['X-Ulams-Tracking-Token' => $token]);
    }

    private function launchToken($user): string
    {
        $url = $this->actingAs($user, 'api')->postJson("/api/scorm/launch/{$this->scoUuid}")->json('data.url');
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->app['auth']->forgetGuards();

        return $fragment['token'];
    }
}
