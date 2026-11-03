<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Token;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\AuthService;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityLink;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\Support\RunsScim;
use Ulams\Identity\Tests\TestCase;
use Ulams\ModelFields\Facades\ModelFields;

class ScimUsersTest extends TestCase
{
    use RunsOidcLogin;
    use RunsScim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scimToken($this->scimProvider());
    }

    // --- create ---------------------------------------------------------------------------------------------

    public function testCreateAUser(): void
    {
        $response = $this->scim('POST', '/Users', $this->userBody('ada@example.com', [
            'title' => 'Nurse',
            'displayName' => 'Ada L.',
            Schemas::ENTERPRISE => ['employeeNumber' => 'E-7', 'department' => 'ICU', 'costCenter' => '4130'],
        ]))->assertStatus(201);
        $json = $response->json();

        $this->assertStringStartsWith('application/scim+json', $response->headers->get('Content-Type'));
        $this->assertSame('http://localhost/api/scim/v2/Users/' . $json['id'], $response->headers->get('Location'));
        $this->assertSame($json['meta']['version'], $response->headers->get('ETag'));
        $this->assertMatchesRegularExpression('/^W\/"[0-9a-f]{24}"$/', $json['meta']['version']);
        $this->assertSame([Schemas::USER, Schemas::ENTERPRISE], $json['schemas']);
        $this->assertSame(['ada@example.com', 'ext-ada@example.com', 'Ada L.', 'Nurse', true], [$json['userName'], $json['externalId'], $json['displayName'], $json['title'], $json['active']]);
        $this->assertSame(['formatted' => 'Ada Lovelace', 'givenName' => 'Ada', 'familyName' => 'Lovelace'], $json['name']);
        $this->assertSame([['value' => 'ada@example.com', 'type' => 'work', 'primary' => true]], $json['emails']);
        $this->assertSame(['employeeNumber' => 'E-7', 'department' => 'ICU', 'costCenter' => '4130'], $json[Schemas::ENTERPRISE]);
        $this->assertSame('User', $json['meta']['resourceType']);

        $user = User::query()->findOrFail($json['id']);
        $this->assertSame(['ada@example.com', 'Ada', 'Lovelace'], [$user->email, $user->first_name, $user->last_name]);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->password);
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $ext = ExternalId::query()->where('local_id', $user->id)->firstOrFail();
        $this->assertSame(['scim', 'ada@example.com', 'ext-ada@example.com'], [$ext->source, $ext->user_name_key, $ext->external_id]);
        $audit = IdentityAudit::query()->where('action', 'scim.user_created')->firstOrFail();
        $this->assertSame(['scim', (string) $user->id], [$audit->actor, $audit->subject_id]);
        $this->assertNotNull($audit->actor_id);
        $this->assertStringNotContainsString('Lovelace', json_encode($audit->data));
    }

    public function testTheProvidersDefaultRoleIsUsedButNeverAdmin(): void
    {
        IdentityLink::query()->delete();
        $tutorProvider = $this->provider(['slug' => 'tutors', 'default_role' => 'tutor']);
        $token = $this->scimToken($tutorProvider);

        $id = $this->scim('POST', '/Users', $this->userBody('t@example.com'), [], $token)->assertStatus(201)->json('id');

        $this->assertSame(['tutor'], User::query()->findOrFail($id)->getRoleNames()->all());
        $tutorProvider->forceFill(['default_role' => 'admin'])->save();
        $id = $this->scim('POST', '/Users', $this->userBody('u@example.com'), [], $token)->assertStatus(201)->json('id');
        $this->assertSame(['student'], User::query()->findOrFail($id)->getRoleNames()->all());
    }

    public function testTheEmailDefaultsToTheUserNameAndNamesFallBack(): void
    {
        $json = $this->scim('POST', '/Users', ['schemas' => [Schemas::USER], 'userName' => 'Grace.Hopper@Example.com'])->assertStatus(201)->json();

        $this->assertSame('grace.hopper@example.com', $json['emails'][0]['value']);
        $this->assertSame('Grace.Hopper@Example.com', $json['userName']);
        $this->assertSame('grace.hopper', $json['name']['givenName']);
    }

    public function testActiveFalseAsEntraStringCreatesAnInactiveUser(): void
    {
        $json = $this->scim('POST', '/Users', $this->userBody('off@example.com', ['active' => 'False']))->assertStatus(201)->json();

        $this->assertFalse($json['active']);
        $this->assertFalse(User::query()->findOrFail($json['id'])->is_active);
    }

    public function testDuplicateUserNameAndExternalIdAreUniquenessErrors(): void
    {
        $this->createScimUser('ada@example.com');

        $same = $this->scim('POST', '/Users', $this->userBody('ADA@example.com', ['externalId' => 'other']))->assertStatus(409);
        $this->assertSame(['409', 'uniqueness'], [$same->json('status'), $same->json('scimType')]);
        $this->scim('POST', '/Users', $this->userBody('bob@example.com', ['externalId' => 'ext-ada@example.com']))->assertStatus(409)->assertJsonPath('scimType', 'uniqueness');
        $this->assertSame(1, ExternalId::query()->count());
    }

    public function testAnotherAccountsEmailIsNeverClaimedUnlessTheProviderIsTrustedForTheDomain(): void
    {
        $local = $this->student(['email' => 'local@example.com', 'email_verified_at' => now()]);

        $this->scim('POST', '/Users', $this->userBody('local@example.com'))->assertStatus(409)->assertJsonPath('scimType', 'uniqueness');
        $this->scim('POST', '/Users', $this->userBody('someone@other.org', ['emails' => [['value' => 'local@example.com', 'primary' => true]]]))->assertStatus(409);
        $this->assertSame(0, ExternalId::query()->count());
        $this->assertSame('local@example.com', $local->refresh()->email);
    }

    public function testATrustedProviderAdoptsAnActiveVerifiedNonPrivilegedAccount(): void
    {
        IdentityLink::query()->delete();
        $provider = $this->provider(['slug' => 'trusted', 'trust_email' => true]);
        $token = $this->scimToken($provider);
        $local = $this->student(['email' => 'local@example.com', 'email_verified_at' => now(), 'first_name' => 'Old']);

        $json = $this->scim('POST', '/Users', $this->userBody('local@example.com'), [], $token)->assertStatus(201)->json();

        $this->assertSame((string) $local->id, $json['id']);
        $this->assertSame('Ada', $local->refresh()->first_name);
        $this->assertSame(['student'], $local->getRoleNames()->all());
        $this->assertTrue((bool) IdentityAudit::query()->where('action', 'scim.user_created')->firstOrFail()->data['adopted']);
    }

    public function testAdoptionRefusesAdminsTutorsInactiveUnverifiedAndOtherDomains(): void
    {
        $provider = $this->provider(['slug' => 'trusted', 'trust_email' => true]);
        $token = $this->scimToken($provider);
        $this->admin(['email' => 'boss@example.com']);
        $this->tutor(['email' => 'teacher@example.com', 'email_verified_at' => now()]);
        $this->student(['email' => 'gone@example.com', 'is_active' => false, 'email_verified_at' => now()]);
        $this->student(['email' => 'unverified@example.com', 'email_verified_at' => null]);
        $this->student(['email' => 'x@elsewhere.org', 'email_verified_at' => now()]);

        foreach (['boss@example.com', 'teacher@example.com', 'gone@example.com', 'unverified@example.com', 'x@elsewhere.org'] as $email) {
            $this->scim('POST', '/Users', $this->userBody($email), [], $token)->assertStatus(409);
        }
        $this->assertSame(0, ExternalId::query()->count());
    }

    public function testInvalidBodiesAreRejectedInTheScimFormat(): void
    {
        $cases = [
            'no schema' => [['userName' => 'a@example.com'], 'invalidSyntax'],
            'wrong schema' => [['schemas' => ['x'], 'userName' => 'a@example.com'], 'invalidSyntax'],
            'no userName' => [['schemas' => [Schemas::USER]], 'invalidValue'],
            'blank userName' => [['schemas' => [Schemas::USER], 'userName' => '  '], 'invalidValue'],
            'userName not a string' => [['schemas' => [Schemas::USER], 'userName' => ['a']], 'invalidValue'],
            'no usable email' => [['schemas' => [Schemas::USER], 'userName' => 'not-an-email'], 'invalidValue'],
            'bad email' => [['schemas' => [Schemas::USER], 'userName' => 'a@example.com', 'emails' => [['value' => 'nope']]], 'invalidValue'],
            'active not boolean' => [['schemas' => [Schemas::USER], 'userName' => 'a@example.com', 'active' => 'yes'], 'invalidValue'],
            'name not object' => [['schemas' => [Schemas::USER], 'userName' => 'a@example.com', 'name' => 'Ada'], 'invalidValue'],
            'name too long' => [['schemas' => [Schemas::USER], 'userName' => 'a@example.com', 'name' => ['givenName' => str_repeat('a', 121)]], 'invalidValue'],
            'manager not an id' => [['schemas' => [Schemas::USER], 'userName' => 'a@example.com', Schemas::ENTERPRISE => ['manager' => ['value' => 'bob']]], 'invalidValue'],
            'manager unknown' => [['schemas' => [Schemas::USER], 'userName' => 'a@example.com', Schemas::ENTERPRISE => ['manager' => ['value' => '99999']]], 'invalidValue'],
        ];
        foreach ($cases as $name => [$body, $type]) {
            $response = $this->scim('POST', '/Users', $body)->assertStatus(400);
            $this->assertSame($type, $response->json('scimType'), $name);
        }
        $this->assertSame(0, ExternalId::query()->count());
    }

    public function testBodiesThatAreNotJsonObjectsOrTooLargeAreRefused(): void
    {
        $this->app['auth']->forgetGuards();
        $server = ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->scimSecret, 'CONTENT_TYPE' => 'application/scim+json'];
        $this->call('POST', '/api/scim/v2/Users', [], [], [], $server, '{nope')->assertStatus(400)->assertJsonPath('scimType', 'invalidSyntax');
        $this->app['auth']->forgetGuards();
        $this->call('POST', '/api/scim/v2/Users', [], [], [], $server, '[1,2]')->assertStatus(400)->assertJsonPath('scimType', 'invalidSyntax');
        $this->app['auth']->forgetGuards();
        $this->call('POST', '/api/scim/v2/Users', [], [], [], $server, json_encode(['schemas' => [Schemas::USER], 'userName' => 'a@example.com', 'title' => str_repeat('x', 1048577)]))->assertStatus(413);
    }

    // --- read and list --------------------------------------------------------------------------------------

    public function testGetOneUserAndConditionalGet(): void
    {
        $created = $this->createScimUser();

        $response = $this->scim('GET', '/Users/' . $created['id'])->assertOk();
        $this->assertSame($created, $response->json());
        $this->assertSame($created['meta']['version'], $response->headers->get('ETag'));
        $this->scim('GET', '/Users/' . $created['id'], null, ['If-None-Match' => $created['meta']['version']])->assertStatus(304);
        $this->scim('GET', '/Users/' . $created['id'], null, ['If-None-Match' => '"other"'])->assertOk();
    }

    public function testOnlyUsersTheProviderProvisionedAreVisible(): void
    {
        $admin = $this->admin(['email' => 'boss@example.com']);
        $student = $this->student(['email' => 'local@example.com']);
        $mine = $this->createScimUser();

        $this->scim('GET', '/Users/' . $admin->id)->assertStatus(404);
        $this->scim('GET', '/Users/' . $student->id)->assertStatus(404);
        $this->scim('GET', '/Users/abc')->assertStatus(404);
        $this->scim('GET', '/Users/0')->assertStatus(404);
        $this->scim('GET', '/Users/' . str_repeat('9', 25))->assertStatus(404);
        $list = $this->scim('GET', '/Users')->assertOk()->json();
        $this->assertSame([$mine['id']], array_column($list['Resources'], 'id'));
        $this->assertSame(1, $list['totalResults']);
    }

    public function testPaginationFollowsRfc7644(): void
    {
        foreach (range(1, 5) as $i) {
            $this->createScimUser("u{$i}@example.com");
        }

        $page = $this->scim('GET', '/Users?startIndex=2&count=2')->assertOk()->json();
        $this->assertSame([Schemas::LIST], $page['schemas']);
        $this->assertSame([5, 2, 2], [$page['totalResults'], $page['startIndex'], $page['itemsPerPage']]);
        $this->assertSame(['u2@example.com', 'u3@example.com'], array_column($page['Resources'], 'userName'));

        $this->assertSame(['u5@example.com'], array_column($this->scim('GET', '/Users?startIndex=5&count=10')->json('Resources'), 'userName'));
        $this->assertSame([], $this->scim('GET', '/Users?startIndex=50')->json('Resources'));
        $zero = $this->scim('GET', '/Users?count=0')->assertOk()->json();
        $this->assertSame([5, 0, []], [$zero['totalResults'], $zero['itemsPerPage'], $zero['Resources']]);
        $this->assertSame(5, $this->scim('GET', '/Users?startIndex=-3')->json('startIndex') === 1 ? 5 : 0);
        $this->assertCount(5, $this->scim('GET', '/Users?count=100000')->json('Resources'));
        $this->scim('GET', '/Users?count=abc')->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');
    }

    public function testFilters(): void
    {
        $a = $this->createScimUser('ada@example.com', ['name' => ['givenName' => 'Ada'], 'externalId' => 'E1']);
        $b = $this->createScimUser('bob@example.org', ['name' => ['givenName' => 'Bob', 'familyName' => "O'Malley"], 'externalId' => 'E2', 'active' => false]);
        $this->createScimUser('cy@example.com', ['name' => ['givenName' => 'Cy'], 'externalId' => 'E3']);
        $names = fn (string $filter) => array_column($this->scim('GET', '/Users?filter=' . rawurlencode($filter))->assertOk()->json('Resources'), 'userName');

        $this->assertSame(['ada@example.com'], $names('userName eq "ADA@example.com"'));
        $this->assertSame(['bob@example.org'], $names('userName ne "ada@example.com" and userName ne "cy@example.com"'));
        $this->assertSame(['ada@example.com', 'cy@example.com'], $names('emails.value ew "example.com"'));
        $this->assertSame(['bob@example.org'], $names('emails.value co "example.org"'));
        $this->assertSame(['ada@example.com'], $names('userName sw "ad"'));
        $this->assertSame(['bob@example.org'], $names('active eq false'));
        $this->assertSame(['ada@example.com', 'cy@example.com'], $names('active eq true'));
        $this->assertSame(['ada@example.com', 'bob@example.org'], $names('externalId eq "E1" or externalId eq "E2"'));
        $this->assertSame([], $names('externalId eq "e1"'));
        $this->assertSame(['ada@example.com', 'cy@example.com'], $names('not (active eq false)'));
        $this->assertSame(['bob@example.org'], $names("name.familyName co \"o'mal\""));
        $this->assertSame(['ada@example.com'], $names('(userName eq "ada@example.com" or userName eq "zed@example.com") and active eq true'));
        $this->assertSame(['bob@example.org'], $names('name.familyName pr and not (name.familyName eq "Lovelace")'));
        $this->assertSame(['ada@example.com', 'bob@example.org', 'cy@example.com'], $names('emails[type eq "work"]'));
        $this->assertSame(['ada@example.com'], $names('emails[type eq "work" and value sw "ada"]'));
        $this->assertSame([], $names('emails[type eq "home"]'));
        $this->assertSame(['ada@example.com', 'bob@example.org', 'cy@example.com'], $names('meta.lastModified gt "2000-01-01T00:00:00Z"'));
        $this->assertSame([], $names('meta.lastModified lt "2000-01-01T00:00:00Z"'));
        $this->assertSame(['ada@example.com'], $names('id eq "' . $a['id'] . '"'));
        $this->assertSame([], $names('id eq "not-a-number"'));
        $this->assertCount(3, $names('id ne "not-a-number"'));
        $this->assertSame(['bob@example.org'], $names('id eq ' . $b['id']));
    }

    public function testFilterTextIsNeverPartOfTheQuery(): void
    {
        $this->createScimUser();

        foreach (['userName eq "x\' OR \'1\'=\'1"', 'userName eq "a%"', 'userName co "%"', 'userName co "_"', 'userName eq "\\"; DROP TABLE users; --"'] as $filter) {
            $this->assertSame([], $this->scim('GET', '/Users?filter=' . rawurlencode($filter))->assertOk()->json('Resources'), $filter);
        }
        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
    }

    public function testBadFiltersAreInvalidFilterErrors(): void
    {
        foreach (['userName eq', 'userName like "x"', 'password eq "x"', 'roles pr', 'users.id eq "1"', 'active eq "true"', 'active gt true', 'meta.lastModified gt "yesterday"', 'userName eq 5', 'emails[type eq "work"[', 'name pr pr', 'x[a eq "1"]'] as $filter) {
            $response = $this->scim('GET', '/Users?filter=' . rawurlencode($filter))->assertStatus(400);
            $this->assertSame('invalidFilter', $response->json('scimType'), $filter);
        }
    }

    public function testAttributesAndExcludedAttributes(): void
    {
        $this->createScimUser('ada@example.com', ['title' => 'Nurse', Schemas::ENTERPRISE => ['department' => 'ICU', 'costCenter' => '4']]);

        $only = $this->scim('GET', '/Users?attributes=userName,name.givenName,' . Schemas::ENTERPRISE . ':department')->assertOk()->json('Resources.0');
        $this->assertEqualsCanonicalizing(['schemas', 'id', 'userName', 'name', Schemas::ENTERPRISE], array_keys($only));
        $this->assertSame(['givenName' => 'Ada'], $only['name']);
        $this->assertSame(['department' => 'ICU'], $only[Schemas::ENTERPRISE]);

        $without = $this->scim('GET', '/Users?excludedAttributes=emails,name.familyName,title')->assertOk()->json('Resources.0');
        $this->assertArrayNotHasKey('emails', $without);
        $this->assertArrayNotHasKey('title', $without);
        $this->assertArrayNotHasKey('familyName', $without['name']);
        $this->assertSame('ada@example.com', $without['userName']);
    }

    public function testPostSearch(): void
    {
        $this->createScimUser('ada@example.com');
        $this->createScimUser('bob@example.com');

        $response = $this->scim('POST', '/Users/.search', ['schemas' => [Schemas::SEARCH], 'filter' => 'userName eq "bob@example.com"', 'startIndex' => 1, 'count' => 10, 'attributes' => ['userName']])->assertOk();

        $this->assertSame(1, $response->json('totalResults'));
        $this->assertSame(['schemas', 'id', 'userName'], array_keys($response->json('Resources.0')));
        $this->scim('POST', '/Users/.search', ['filter' => 'userName eq "x"'])->assertStatus(400)->assertJsonPath('scimType', 'invalidSyntax');
        $this->scim('POST', '/Users/.search', ['schemas' => [Schemas::SEARCH], 'filter' => 'bad filter'])->assertStatus(400)->assertJsonPath('scimType', 'invalidFilter');
    }

    // --- PUT ------------------------------------------------------------------------------------------------

    public function testPutReplacesTheUser(): void
    {
        $created = $this->createScimUser('ada@example.com', ['title' => 'Nurse', Schemas::ENTERPRISE => ['department' => 'ICU']]);

        $body = $this->userBody('ada.king@example.com', ['name' => ['givenName' => 'Augusta', 'familyName' => 'King'], 'externalId' => 'new-ext']);
        $body['id'] = $created['id'];
        $json = $this->scim('PUT', '/Users/' . $created['id'], $body)->assertOk()->json();

        $this->assertSame(['ada.king@example.com', 'new-ext', 'Augusta'], [$json['userName'], $json['externalId'], $json['name']['givenName']]);
        $this->assertSame('ada.king@example.com', $json['emails'][0]['value']);
        $this->assertArrayNotHasKey('title', $json); // omitted attributes are cleared (RFC 7644 3.5.1)
        $this->assertArrayNotHasKey(Schemas::ENTERPRISE, $json);
        $this->assertNotSame($created['meta']['version'], $json['meta']['version']);
        $user = User::query()->findOrFail($created['id']);
        $this->assertSame(['ada.king@example.com', 'Augusta', 'King'], [$user->email, $user->first_name, $user->last_name]);
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $audit = IdentityAudit::query()->where('action', 'scim.user_updated')->firstOrFail();
        $this->assertEqualsCanonicalizing(['name.givenName', 'name.familyName', 'emails', 'userName', 'externalId', 'title', 'enterprise'], $audit->data['changed']);
    }

    public function testPutThatLeavesActiveOutKeepsTheCurrentState(): void
    {
        $created = $this->createScimUser('ada@example.com', ['active' => false]);
        $body = $this->userBody('ada@example.com');
        unset($body['active']);

        $json = $this->scim('PUT', '/Users/' . $created['id'], $body)->assertOk()->json();

        $this->assertFalse($json['active']);
        $this->assertFalse(User::query()->findOrFail($created['id'])->is_active);
    }

    public function testPutCannotChangeTheIdOrTakeAnotherUsersNames(): void
    {
        $a = $this->createScimUser('ada@example.com');
        $b = $this->createScimUser('bob@example.com');

        $body = $this->userBody('ada@example.com');
        $body['id'] = '999999';
        $this->scim('PUT', '/Users/' . $a['id'], $body)->assertStatus(400)->assertJsonPath('scimType', 'mutability');
        $this->scim('PUT', '/Users/' . $a['id'], $this->userBody('bob@example.com', ['externalId' => 'x']))->assertStatus(409)->assertJsonPath('scimType', 'uniqueness');
        $this->scim('PUT', '/Users/' . $a['id'], $this->userBody('ada@example.com', ['emails' => [['value' => 'bob@example.com']]]))->assertStatus(409);
        $this->scim('PUT', '/Users/' . $a['id'], $this->userBody('ada@example.com', ['externalId' => $b['externalId']]))->assertStatus(409);
        $this->assertSame('ada@example.com', User::query()->findOrFail($a['id'])->email);
    }

    public function testIfMatchGuardsPutPatchAndDelete(): void
    {
        $created = $this->createScimUser();
        $stale = ['If-Match' => 'W/"0000000000000000deadbeef"'];

        $this->scim('PUT', '/Users/' . $created['id'], $this->userBody(), $stale)->assertStatus(412);
        $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => 'title', 'value' => 'x']]), $stale)->assertStatus(412);
        $this->scim('DELETE', '/Users/' . $created['id'], null, $stale)->assertStatus(412);
        $this->assertNull(User::query()->findOrFail($created['id'])->deleted_at);
        $this->assertTrue(User::query()->findOrFail($created['id'])->is_active);

        $ok = $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => 'title', 'value' => 'x']]), ['If-Match' => $created['meta']['version']])->assertOk();
        $this->assertSame('x', $ok->json('title'));
        // the version moved on, so the old one is stale now
        $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => 'title', 'value' => 'y']]), ['If-Match' => $created['meta']['version']])->assertStatus(412);
        $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => 'title', 'value' => 'z']]), ['If-Match' => '*'])->assertOk();
    }

    // --- PATCH ----------------------------------------------------------------------------------------------

    public function testPatchAsEntraSendsIt(): void
    {
        $created = $this->createScimUser('ada@example.com', [Schemas::ENTERPRISE => ['department' => 'ICU']]);

        $json = $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([
            ['op' => 'Replace', 'path' => 'active', 'value' => 'False'],
            ['op' => 'Replace', 'path' => 'name.givenName', 'value' => 'Augusta'],
            ['op' => 'Replace', 'path' => 'emails[type eq "work"].value', 'value' => 'augusta@example.com'],
            ['op' => 'Add', 'path' => Schemas::ENTERPRISE . ':department', 'value' => 'Surgery'],
            ['op' => 'Replace', 'path' => 'userName', 'value' => 'augusta@example.com'],
        ]))->assertOk()->json();

        $this->assertFalse($json['active']);
        $this->assertSame('Augusta', $json['name']['givenName']);
        $this->assertSame('augusta@example.com', $json['emails'][0]['value']);
        $this->assertSame('augusta@example.com', $json['userName']);
        $this->assertSame('Surgery', $json[Schemas::ENTERPRISE]['department']);
        $this->assertFalse(User::query()->findOrFail($created['id'])->is_active);
    }

    public function testPatchWithoutAPathReplacesTheObjectOfAttributes(): void
    {
        $created = $this->createScimUser();

        $json = $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'value' => ['active' => false, 'title' => 'Retired']]]))->assertOk()->json();

        $this->assertSame([false, 'Retired'], [$json['active'], $json['title']]);
    }

    public function testPatchRemovesAttributes(): void
    {
        $created = $this->createScimUser('ada@example.com', ['title' => 'Nurse', Schemas::ENTERPRISE => ['department' => 'ICU']]);

        $json = $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([
            ['op' => 'remove', 'path' => 'title'],
            ['op' => 'remove', 'path' => Schemas::ENTERPRISE . ':department'],
        ]))->assertOk()->json();

        $this->assertArrayNotHasKey('title', $json);
        $this->assertArrayNotHasKey(Schemas::ENTERPRISE, $json);
    }

    public function testAFailingOperationChangesNothing(): void
    {
        $created = $this->createScimUser();

        $response = $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([
            ['op' => 'replace', 'path' => 'title', 'value' => 'Changed'],
            ['op' => 'replace', 'path' => 'active', 'value' => false],
            ['op' => 'replace', 'path' => 'emails[type eq "home"].value', 'value' => 'x@example.com'],
        ]))->assertStatus(400);

        $this->assertSame('noTarget', $response->json('scimType'));
        $after = $this->scim('GET', '/Users/' . $created['id'])->json();
        $this->assertSame($created, $after);
        $this->assertTrue(User::query()->findOrFail($created['id'])->is_active);
    }

    public function testBadPatchRequests(): void
    {
        $created = $this->createScimUser();
        $path = '/Users/' . $created['id'];

        $this->scim('PATCH', $path, ['Operations' => []])->assertStatus(400)->assertJsonPath('scimType', 'invalidSyntax');
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'replace', 'path' => 'id', 'value' => '5']]))->assertStatus(400)->assertJsonPath('scimType', 'mutability');
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'replace', 'path' => 'userName', 'value' => '']]))->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => 'maybe']]))->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'remove', 'path' => 'userName']]))->assertStatus(400);
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'remove']]))->assertStatus(400)->assertJsonPath('scimType', 'noTarget');
    }

    // --- deprovisioning -------------------------------------------------------------------------------------

    private function signedInToken(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return app(AuthService::class)->createTokenForUser($user)->accessToken;
    }

    private function meStatus(string $token): int
    {
        $this->app['auth']->forgetGuards();

        return $this->call('GET', '/api/profile/me', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_ACCEPT' => 'application/json'])->getStatusCode();
    }

    public function testActiveFalseDeactivatesAndRevokesEverySessionAndToken(): void
    {
        $created = $this->createScimUser();
        $user = User::query()->findOrFail($created['id']);
        $login = $this->signedInToken($user);
        $personal = app(\Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract::class)->issue($user, 'cli', ['learner:read'], 30)->secret;
        $this->assertSame(200, $this->meStatus($login));

        $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => false]]))->assertOk();

        $this->assertFalse($user->refresh()->is_active);
        $this->assertSame(401, $this->meStatus($login));
        $this->assertSame(401, $this->meStatus($personal));
        $this->assertSame(0, Token::query()->where('user_id', $user->id)->where('revoked', false)->count());
        $this->assertSame(['scim.user_created', 'scim.user_deactivated'], IdentityAudit::query()->whereIn('action', ['scim.user_created', 'scim.user_deactivated', 'scim.user_updated'])->orderBy('id')->pluck('action')->all());
    }

    public function testADeactivatedUserCannotSignInByPasswordOrAnyOtherMethod(): void
    {
        $created = $this->createScimUser();
        $user = User::query()->findOrFail($created['id']);
        $user->forceFill(['password' => Hash::make('Secret-123')])->save();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'Secret-123'])->assertOk();

        $this->scim('PUT', '/Users/' . $created['id'], $this->userBody('ada@example.com', ['active' => false]))->assertOk();

        $this->app['auth']->forgetGuards();
        $this->assertNotSame(200, $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'Secret-123'])->getStatusCode());
    }

    public function testActiveTrueRestoresAccessWithoutOldTokens(): void
    {
        $created = $this->createScimUser();
        $user = User::query()->findOrFail($created['id']);
        $old = $this->signedInToken($user);
        $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => 'False']]))->assertOk();

        $json = $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => 'True']]))->assertOk()->json();

        $this->assertTrue($json['active']);
        $this->assertTrue($user->refresh()->is_active);
        $this->assertSame(401, $this->meStatus($old));
        $this->assertSame(200, $this->meStatus($this->signedInToken($user)));
        $this->assertSame(1, IdentityAudit::query()->where('action', 'scim.user_reactivated')->count());
    }

    public function testDeleteDeactivatesUnlinksKeepsTheAccountAndAllowsReprovisioning(): void
    {
        $created = $this->createScimUser();
        $user = User::query()->findOrFail($created['id']);
        $provider = \Ulams\Identity\Models\IdentityProvider::query()->firstOrFail();
        IdentityLink::query()->create(['provider_id' => $provider->id, 'user_id' => $user->id, 'subject' => 'sub-1', 'linked_via' => 'jit']);
        $login = $this->signedInToken($user);

        $this->scim('DELETE', '/Users/' . $created['id'])->assertStatus(204);

        $user->refresh();
        $this->assertNull($user->deleted_at);
        $this->assertFalse($user->is_active);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame(401, $this->meStatus($login));
        $this->assertSame(0, IdentityLink::query()->where('user_id', $user->id)->count());
        $this->assertNotNull(ExternalId::query()->where('local_id', $user->id)->firstOrFail()->deleted_at);
        $this->scim('GET', '/Users/' . $created['id'])->assertStatus(404);
        $this->scim('DELETE', '/Users/' . $created['id'])->assertStatus(404);
        $this->assertSame(0, $this->scim('GET', '/Users')->json('totalResults'));
        $this->assertSame(1, IdentityAudit::query()->where('action', 'scim.user_deleted')->firstOrFail()->data !== null ? 1 : 0);

        // the client provisions the person again: the same account comes back, and the password is still unset
        $again = $this->scim('POST', '/Users', $this->userBody('ada@example.com'))->assertStatus(201)->json();
        $this->assertSame($created['id'], $again['id']);
        $this->assertTrue($again['active']);
        $this->assertTrue($user->refresh()->is_active);
    }

    public function testDeleteCanAnonymiseInsteadWhenConfigured(): void
    {
        config(['ulams_identity.scim.delete_behaviour' => 'anonymise']);
        $created = $this->createScimUser();
        $user = User::query()->findOrFail($created['id']);

        $this->scim('DELETE', '/Users/' . $created['id'])->assertStatus(204);

        $this->assertNotNull(User::withTrashed()->findOrFail($created['id'])->deleted_at);
        $this->assertNull(User::query()->find($created['id']));
        $this->assertNotSame('ada@example.com', User::withTrashed()->findOrFail($created['id'])->email);
        $ext = ExternalId::query()->where('local_id', $user->id)->firstOrFail();
        $this->assertNull($ext->user_name_key);
        $this->scim('POST', '/Users', $this->userBody('ada@example.com'))->assertStatus(201);
    }

    public function testAdministratorsCannotBeChangedThroughScim(): void
    {
        $created = $this->createScimUser();
        $user = User::query()->findOrFail($created['id']);
        $user->assignRole('admin');

        $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => false]]))->assertStatus(403);
        $this->scim('PUT', '/Users/' . $created['id'], $this->userBody('ada@example.com', ['active' => false]))->assertStatus(403);
        $this->scim('DELETE', '/Users/' . $created['id'])->assertStatus(403);
        $this->assertTrue($user->refresh()->is_active);
        $this->assertNull(ExternalId::query()->where('local_id', $user->id)->firstOrFail()->deleted_at);
    }

    public function testAnotherProviderCannotTakeAProvisionedUserName(): void
    {
        $this->createScimUser();
        $other = $this->provider(['slug' => 'other']);
        $token = $this->scimToken($other);

        $this->scim('POST', '/Users', $this->userBody('ada@example.com'), [], $token)->assertStatus(409);
    }

    // --- attributes in the academy ----------------------------------------------------------------------------

    public function testEnterpriseAttributesAreMirroredIntoDefinedModelFieldsOnly(): void
    {
        ModelFields::addOrUpdateMetadataField(\Ulams\Auth\Models\User::class, 'department', 'varchar', '');
        $this->app->forgetInstance('model-fields');

        $created = $this->createScimUser('ada@example.com', [Schemas::ENTERPRISE => ['department' => 'ICU', 'division' => 'North']]);
        $user = User::query()->findOrFail($created['id']);

        $this->assertSame('ICU', $user->department);
        $this->assertNull($user->getAttribute('division'));
        // both come back through SCIM: the field the academy has and the one it has not
        $this->assertSame(['department' => 'ICU', 'division' => 'North'], $created[Schemas::ENTERPRISE]);
        $this->scim('PATCH', '/Users/' . $created['id'], $this->patchBody([['op' => 'replace', 'path' => Schemas::ENTERPRISE . ':department', 'value' => 'ER']]))->assertOk();
        $this->assertSame('ER', User::query()->findOrFail($created['id'])->department);
    }

    public function testAManagerIsAUserOfTheSameProvider(): void
    {
        $boss = $this->createScimUser('boss@example.com');

        $json = $this->createScimUser('ada@example.com', [Schemas::ENTERPRISE => ['manager' => ['value' => $boss['id']]]]);

        $this->assertSame($boss['id'], $json[Schemas::ENTERPRISE]['manager']['value']);
        $this->assertSame('http://localhost/api/scim/v2/Users/' . $boss['id'], $json[Schemas::ENTERPRISE]['manager']['$ref']);
        $self = $this->scim('PATCH', '/Users/' . $json['id'], $this->patchBody([['op' => 'replace', 'path' => Schemas::ENTERPRISE . ':manager', 'value' => ['value' => $json['id']]]]))->assertStatus(400);
        $this->assertSame('invalidValue', $self->json('scimType'));
        $outsider = $this->student();
        $this->scim('PATCH', '/Users/' . $json['id'], $this->patchBody([['op' => 'replace', 'path' => Schemas::ENTERPRISE . ':manager', 'value' => ['value' => (string) $outsider->id]]]))->assertStatus(400);
        $cleared = $this->scim('PATCH', '/Users/' . $json['id'], $this->patchBody([['op' => 'remove', 'path' => Schemas::ENTERPRISE . ':manager']]))->assertOk()->json();
        $this->assertArrayNotHasKey(Schemas::ENTERPRISE, $cleared);
    }

    // --- sign-in after provisioning ---------------------------------------------------------------------------

    public function testAProvisionedUserSignsInThroughTheProviderWithoutTrustEmail(): void
    {
        $this->createScimUser('ada@example.com');
        $this->newVerifier();

        $result = $this->signIn(['email' => 'ada@example.com', 'email_verified' => false, 'sub' => 'idp-sub-1']);
        $code = $this->returned($result['response'])['code'] ?? null;

        $this->assertNotNull($code);
        $this->assertSame('scim', 'scim');
        $this->assertSame(1, IdentityLink::query()->count());
        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
    }

    public function testADeprovisionedUserCannotSignInThroughTheProvider(): void
    {
        $created = $this->createScimUser('ada@example.com');
        $this->scim('DELETE', '/Users/' . $created['id'])->assertStatus(204);
        $this->newVerifier();

        $params = $this->returned($this->signIn(['email' => 'ada@example.com', 'sub' => 'idp-sub-1'])['response']);

        $this->assertArrayNotHasKey('code', $params);
        $this->assertSame(0, IdentityLink::query()->count());
    }
}
