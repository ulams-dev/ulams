<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Events\UserAddedToGroup;
use Ulams\Auth\Events\UserRemovedFromGroup;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\ExternalId;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Tests\Support\RunsScim;
use Ulams\Identity\Tests\TestCase;

class ScimGroupsTest extends TestCase
{
    use RunsScim;

    private array $ann;
    private array $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scimToken($this->scimProvider());
        $this->ann = $this->createScimUser('ann@example.com');
        $this->bob = $this->createScimUser('bob@example.com');
    }

    private function members(int|string $groupId): array
    {
        return DB::table('group_user')->where('group_id', $groupId)->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (string) $id)->all();
    }

    // --- create and read -----------------------------------------------------------------------------------

    public function testCreateAGroupWithMembers(): void
    {
        $response = $this->scim('POST', '/Groups', $this->groupBody('Nurses', [$this->ann['id'], $this->bob['id']]))->assertStatus(201);
        $json = $response->json();

        $this->assertStringStartsWith('application/scim+json', $response->headers->get('Content-Type'));
        $this->assertSame('http://localhost/api/scim/v2/Groups/' . $json['id'], $response->headers->get('Location'));
        $this->assertSame($json['meta']['version'], $response->headers->get('ETag'));
        $this->assertSame([Schemas::GROUP], $json['schemas']);
        $this->assertSame(['Nurses', 'g-Nurses'], [$json['displayName'], $json['externalId']]);
        $this->assertSame([$this->ann['id'], $this->bob['id']], array_column($json['members'], 'value'));
        $this->assertSame(['value' => $this->ann['id'], '$ref' => 'http://localhost/api/scim/v2/Users/' . $this->ann['id'], 'type' => 'User', 'display' => 'Ada Lovelace'], $json['members'][0]);
        $this->assertSame('Group', $json['meta']['resourceType']);

        $group = Group::query()->findOrFail($json['id']);
        $this->assertSame('Nurses', $group->name);
        $this->assertFalse($group->registerable);
        $this->assertSame([$this->ann['id'], $this->bob['id']], $this->members($group->id));
        $this->assertSame('scim', ExternalId::query()->where(['resource_type' => 'group', 'local_id' => $group->id])->firstOrFail()->source);
        $audit = IdentityAudit::query()->where('action', 'scim.group_created')->firstOrFail();
        $this->assertSame(['group', (string) $group->id, 2], [$audit->subject_type, $audit->subject_id, $audit->data['members']]);
        $this->assertStringNotContainsString('Nurses', json_encode($audit->data));
    }

    public function testAnEmptyGroupAndTheUsersGroupsAttribute(): void
    {
        $empty = $this->createScimGroup('Empty');
        $this->assertArrayNotHasKey('members', $empty);
        $group = $this->createScimGroup('Nurses', [$this->ann['id']]);

        $user = $this->scim('GET', '/Users/' . $this->ann['id'])->assertOk()->json();

        $this->assertSame([['value' => $group['id'], '$ref' => 'http://localhost/api/scim/v2/Groups/' . $group['id'], 'display' => 'Nurses']], $user['groups']);
        $this->assertArrayNotHasKey('groups', $this->scim('GET', '/Users/' . $this->bob['id'])->json());
        // groups is read-only
        $this->scim('PATCH', '/Users/' . $this->ann['id'], $this->patchBody([['op' => 'remove', 'path' => 'groups']]))->assertStatus(400)->assertJsonPath('scimType', 'mutability');
    }

    public function testDuplicateDisplayNamesAndExternalIdsAreUniquenessErrors(): void
    {
        $this->createScimGroup('Nurses');
        Group::query()->create(['name' => 'Admins group']);

        $this->scim('POST', '/Groups', $this->groupBody('nurses', [], ['externalId' => 'x']))->assertStatus(409)->assertJsonPath('scimType', 'uniqueness');
        $this->scim('POST', '/Groups', $this->groupBody('Other', [], ['externalId' => 'g-Nurses']))->assertStatus(409)->assertJsonPath('scimType', 'uniqueness');
        // a group the client did not create is not claimed by name
        $this->scim('POST', '/Groups', $this->groupBody('admins GROUP'))->assertStatus(409)->assertJsonPath('scimType', 'uniqueness');
        $this->assertSame(2, Group::query()->count());
    }

    public function testMembersMustBeUsersOfThisProvider(): void
    {
        $outsider = $this->student();
        $admin = $this->admin();

        foreach ([[(string) $outsider->id], [(string) $admin->id], ['99999'], [$this->ann['id'], '99999']] as $members) {
            $this->scim('POST', '/Groups', $this->groupBody('G', $members))->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');
        }
        $this->scim('POST', '/Groups', $this->groupBody('G', [], ['members' => [['value' => $this->ann['id'], 'type' => 'Group']]]))->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');
        $this->scim('POST', '/Groups', $this->groupBody('G', [], ['members' => [['value' => 'abc']]]))->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');
        $this->scim('POST', '/Groups', $this->groupBody('G', [], ['members' => 'x']))->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');
        $this->scim('POST', '/Groups', ['schemas' => [Schemas::GROUP]])->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');
        $this->scim('POST', '/Groups', ['displayName' => 'G'])->assertStatus(400)->assertJsonPath('scimType', 'invalidSyntax');
        $this->assertSame(0, Group::query()->count());
    }

    public function testGetListFilterAndPaginate(): void
    {
        $a = $this->createScimGroup('Alpha', [$this->ann['id']]);
        $b = $this->createScimGroup('Beta', [$this->bob['id']]);
        $this->createScimGroup('Gamma');
        $names = fn (string $q) => array_column($this->scim('GET', '/Groups?' . $q)->assertOk()->json('Resources'), 'displayName');

        $this->assertSame($a, $this->scim('GET', '/Groups/' . $a['id'])->assertOk()->json());
        $this->scim('GET', '/Groups/' . $a['id'], null, ['If-None-Match' => $a['meta']['version']])->assertStatus(304);
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $names(''));
        $this->assertSame(['Beta'], $names('filter=' . rawurlencode('displayName eq "BETA"')));
        $this->assertSame(['Alpha', 'Gamma'], $names('filter=' . rawurlencode('displayName sw "a" or displayName ew "ma"')));
        $this->assertSame(['Alpha'], $names('filter=' . rawurlencode('members[value eq "' . $this->ann['id'] . '"]')));
        $this->assertSame(['Beta'], $names('filter=' . rawurlencode('members.value eq "' . $this->bob['id'] . '"')));
        $this->assertSame(['Alpha', 'Gamma'], $names('filter=' . rawurlencode('not (members.value eq "' . $this->bob['id'] . '")')));
        $this->assertSame(['Alpha'], $names('filter=' . rawurlencode('externalId eq "g-Alpha"')));
        $this->assertSame(['Beta', 'Gamma'], $names('startIndex=2&count=5'));
        $page = $this->scim('GET', '/Groups?count=1&startIndex=2')->json();
        $this->assertSame([3, 1, 2], [$page['totalResults'], $page['itemsPerPage'], $page['startIndex']]);
        $this->scim('GET', '/Groups?filter=' . rawurlencode('members.value co "1"'))->assertStatus(400)->assertJsonPath('scimType', 'invalidFilter');
        $this->scim('GET', '/Groups?filter=' . rawurlencode('name eq "x"'))->assertStatus(400)->assertJsonPath('scimType', 'invalidFilter');
        $slim = $this->scim('GET', '/Groups?excludedAttributes=members')->json('Resources.0');
        $this->assertArrayNotHasKey('members', $slim);
        $this->assertSame($b['id'], $this->scim('POST', '/Groups/.search', ['schemas' => [Schemas::SEARCH], 'filter' => 'displayName eq "Beta"'])->json('Resources.0.id'));
    }

    public function testAFilterCannotProbeAccountsTheProviderDoesNotManage(): void
    {
        $outsider = $this->student();
        $group = $this->createScimGroup('Nurses');
        // an administrator put an unmanaged account into the managed group by hand
        Group::query()->findOrFail($group['id'])->users()->attach($outsider->id);

        $found = $this->scim('GET', '/Groups?filter=' . rawurlencode('members.value eq "' . $outsider->id . '"'))->assertOk()->json();
        $this->assertSame(0, $found['totalResults']);
        $shown = $this->scim('GET', '/Groups/' . $group['id'])->assertOk()->json();
        $this->assertArrayNotHasKey('members', $shown);
    }

    // --- PUT and PATCH ---------------------------------------------------------------------------------------

    public function testPutReplacesNameAndMembersAndFiresTheMembershipEvents(): void
    {
        $group = $this->createScimGroup('Nurses', [$this->ann['id']]);
        \Illuminate\Support\Facades\Event::fake([UserAddedToGroup::class, UserRemovedFromGroup::class]);

        $json = $this->scim('PUT', '/Groups/' . $group['id'], $this->groupBody('Night nurses', [$this->bob['id']], ['id' => $group['id']]))->assertOk()->json();

        $this->assertSame('Night nurses', $json['displayName']);
        $this->assertSame([$this->bob['id']], array_column($json['members'], 'value'));
        $this->assertSame([$this->bob['id']], $this->members($group['id']));
        \Illuminate\Support\Facades\Event::assertDispatchedTimes(UserAddedToGroup::class, 1);
        \Illuminate\Support\Facades\Event::assertDispatchedTimes(UserRemovedFromGroup::class, 1);
        $this->assertNotSame($group['meta']['version'], $json['meta']['version']);
        $this->assertSame(['added' => 1, 'removed' => 1], IdentityAudit::query()->where('action', 'scim.group_members_changed')->latest('id')->firstOrFail()->data);
    }

    public function testPutWithoutMembersKeepsTheMembership(): void
    {
        $group = $this->createScimGroup('Nurses', [$this->ann['id']]);
        $body = $this->groupBody('Renamed');
        unset($body['members']);

        $this->scim('PUT', '/Groups/' . $group['id'], $body)->assertOk();

        $this->assertSame([$this->ann['id']], $this->members($group['id']));
        $this->assertSame('Renamed', Group::query()->findOrFail($group['id'])->name);
    }

    public function testPutWithAnEmptyMemberListEmptiesTheGroupButNotAdminAddedMembers(): void
    {
        $group = $this->createScimGroup('Nurses', [$this->ann['id']]);
        $outsider = $this->student();
        Group::query()->findOrFail($group['id'])->users()->attach($outsider->id);

        $this->scim('PUT', '/Groups/' . $group['id'], $this->groupBody('Nurses', []))->assertOk();

        $this->assertSame([(string) $outsider->id], $this->members($group['id']));
    }

    public function testPutCannotChangeTheIdOrTakeANameInUse(): void
    {
        $a = $this->createScimGroup('Alpha');
        $this->createScimGroup('Beta');

        $this->scim('PUT', '/Groups/' . $a['id'], $this->groupBody('Alpha', [], ['id' => '999999']))->assertStatus(400)->assertJsonPath('scimType', 'mutability');
        $this->scim('PUT', '/Groups/' . $a['id'], $this->groupBody('beta'))->assertStatus(409);
        $this->scim('PUT', '/Groups/' . $a['id'], $this->groupBody('Alpha', [], ['externalId' => 'g-Beta']))->assertStatus(409);
        $this->scim('PUT', '/Groups/' . $a['id'], $this->groupBody('alpha'))->assertOk(); // its own name, other case
    }

    public function testPatchAsOktaAndEntraSendIt(): void
    {
        $group = $this->createScimGroup('Nurses');
        $path = '/Groups/' . $group['id'];

        // Okta: add members
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'add', 'path' => 'members', 'value' => [['value' => $this->ann['id']], ['value' => $this->bob['id']]]]]))->assertOk();
        $this->assertSame([$this->ann['id'], $this->bob['id']], $this->members($group['id']));
        // Okta: remove one member by value path
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'remove', 'path' => 'members[value eq "' . $this->ann['id'] . '"]']]))->assertOk();
        $this->assertSame([$this->bob['id']], $this->members($group['id']));
        // Entra: capitalised ops, remove by listing the members to remove, rename without a path
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'Add', 'path' => 'members', 'value' => [['value' => $this->ann['id']]]]]))->assertOk();
        $json = $this->scim('PATCH', $path, $this->patchBody([
            ['op' => 'Remove', 'path' => 'members', 'value' => [['value' => $this->bob['id']]]],
            ['op' => 'Replace', 'value' => ['displayName' => 'Day nurses']],
        ]))->assertOk()->json();
        $this->assertSame([$this->ann['id']], $this->members($group['id']));
        $this->assertSame('Day nurses', $json['displayName']);
        // replace and remove all
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'replace', 'path' => 'members', 'value' => [['value' => $this->bob['id']]]]]))->assertOk();
        $this->assertSame([$this->bob['id']], $this->members($group['id']));
        $this->scim('PATCH', $path, $this->patchBody([['op' => 'remove', 'path' => 'members']]))->assertOk();
        $this->assertSame([], $this->members($group['id']));
    }

    public function testAFailingPatchOperationChangesNothing(): void
    {
        $group = $this->createScimGroup('Nurses', [$this->ann['id']]);

        $this->scim('PATCH', '/Groups/' . $group['id'], $this->patchBody([
            ['op' => 'add', 'path' => 'members', 'value' => [['value' => $this->bob['id']]]],
            ['op' => 'replace', 'path' => 'displayName', 'value' => 'Changed'],
            ['op' => 'add', 'path' => 'members', 'value' => [['value' => '99999']]],
        ]))->assertStatus(400)->assertJsonPath('scimType', 'invalidValue');

        $this->assertSame($group, $this->scim('GET', '/Groups/' . $group['id'])->json());
        $this->assertSame([$this->ann['id']], $this->members($group['id']));
        $this->scim('PATCH', '/Groups/' . $group['id'], $this->patchBody([['op' => 'remove', 'path' => 'members[value eq "' . $this->bob['id'] . '"].x.y']]))->assertStatus(400);
    }

    public function testIfMatchGuardsGroupWrites(): void
    {
        $group = $this->createScimGroup('Nurses');
        $stale = ['If-Match' => 'W/"000000000000000000dead"'];

        $this->scim('PUT', '/Groups/' . $group['id'], $this->groupBody('X'), $stale)->assertStatus(412);
        $this->scim('PATCH', '/Groups/' . $group['id'], $this->patchBody([['op' => 'replace', 'path' => 'displayName', 'value' => 'X']]), $stale)->assertStatus(412);
        $this->scim('DELETE', '/Groups/' . $group['id'], null, $stale)->assertStatus(412);
        $this->assertSame('Nurses', Group::query()->findOrFail($group['id'])->name);
        $this->scim('DELETE', '/Groups/' . $group['id'], null, ['If-Match' => $group['meta']['version']])->assertStatus(204);
    }

    // --- delete ---------------------------------------------------------------------------------------------

    public function testDeleteRemovesTheGroupButNotThePeople(): void
    {
        $group = $this->createScimGroup('Nurses', [$this->ann['id']]);

        $this->scim('DELETE', '/Groups/' . $group['id'])->assertStatus(204);

        $this->assertNull(Group::query()->find($group['id']));
        $this->assertNotNull(Group::withTrashed()->find($group['id']));
        $this->assertTrue(User::query()->findOrFail($this->ann['id'])->is_active);
        $this->scim('GET', '/Groups/' . $group['id'])->assertStatus(404);
        $this->scim('DELETE', '/Groups/' . $group['id'])->assertStatus(404);
        $this->assertSame(0, $this->scim('GET', '/Groups')->json('totalResults'));
        $this->assertArrayNotHasKey('groups', $this->scim('GET', '/Users/' . $this->ann['id'])->json());
        $this->assertSame(1, IdentityAudit::query()->where('action', 'scim.group_deleted')->count());
        // the name and the externalId can be used again
        $this->createScimGroup('Nurses');
    }

    public function testADeprovisionedUserLeavesTheGroups(): void
    {
        $group = $this->createScimGroup('Nurses', [$this->ann['id'], $this->bob['id']]);
        $this->scim('PATCH', '/Users/' . $this->bob['id'], $this->patchBody([['op' => 'replace', 'path' => 'active', 'value' => false]]))->assertOk();
        $this->assertSame([$this->ann['id'], $this->bob['id']], $this->members($group['id'])); // deactivation keeps them, so reactivation restores

        $this->scim('DELETE', '/Users/' . $this->ann['id'])->assertStatus(204);

        $this->assertSame([$this->bob['id']], $this->members($group['id']));
    }

    // --- roles from groups ----------------------------------------------------------------------------------

    private function mapRoles(array $map): void
    {
        $this->actingAs($this->admin(), 'api')->putJson('/api/admin/identity/providers/' . \Ulams\Identity\Models\IdentityProvider::query()->firstOrFail()->id, ['scim' => ['group_roles' => $map]])->assertOk();
    }

    public function testMembersOfAMappedGroupGetTheRoleWhileTheyAreMembers(): void
    {
        $this->mapRoles(['Instructors' => 'tutor']);
        $ann = User::query()->findOrFail($this->ann['id']);
        $this->assertSame(['student'], $ann->getRoleNames()->all());

        $group = $this->createScimGroup('instructors', [$this->ann['id']]);
        $this->assertEqualsCanonicalizing(['student', 'tutor'], $ann->refresh()->getRoleNames()->all());
        $this->assertSame(['student'], User::query()->findOrFail($this->bob['id'])->getRoleNames()->all());
        $granted = IdentityAudit::query()->where('action', 'role.granted_by_mapping')->firstOrFail();
        $this->assertSame([(string) $ann->id, ['role' => 'tutor']], [$granted->subject_id, $granted->data]);

        $this->scim('PATCH', '/Groups/' . $group['id'], $this->patchBody([['op' => 'add', 'path' => 'members', 'value' => [['value' => $this->bob['id']]]]]))->assertOk();
        $this->assertContains('tutor', User::query()->findOrFail($this->bob['id'])->getRoleNames()->all());

        $this->scim('PATCH', '/Groups/' . $group['id'], $this->patchBody([['op' => 'remove', 'path' => 'members[value eq "' . $this->ann['id'] . '"]']]))->assertOk();
        $this->assertSame(['student'], $ann->refresh()->getRoleNames()->all());
        $this->assertSame(1, IdentityAudit::query()->where('action', 'role.revoked_by_mapping')->count());

        $this->scim('DELETE', '/Groups/' . $group['id'])->assertStatus(204);
        $this->assertSame(['student'], User::query()->findOrFail($this->bob['id'])->getRoleNames()->all());
    }

    public function testARoleThePersonAlreadyHadIsNeverTakenAway(): void
    {
        $this->mapRoles(['Instructors' => 'tutor']);
        $ann = User::query()->findOrFail($this->ann['id']);
        $ann->assignRole('tutor');
        $group = $this->createScimGroup('Instructors', [$this->ann['id']]);

        $this->scim('DELETE', '/Groups/' . $group['id'])->assertStatus(204);

        $this->assertContains('tutor', $ann->refresh()->getRoleNames()->all());
        $this->assertSame(0, IdentityAudit::query()->whereIn('action', ['role.granted_by_mapping', 'role.revoked_by_mapping'])->count());
    }

    public function testRenamingAGroupMovesTheRoleWithTheMapping(): void
    {
        $this->mapRoles(['Instructors' => 'tutor']);
        $group = $this->createScimGroup('Instructors', [$this->ann['id']]);
        $ann = User::query()->findOrFail($this->ann['id']);
        $this->assertContains('tutor', $ann->getRoleNames()->all());

        $this->scim('PATCH', '/Groups/' . $group['id'], $this->patchBody([['op' => 'replace', 'path' => 'displayName', 'value' => 'Staff']]))->assertOk();
        $this->assertNotContains('tutor', $ann->refresh()->getRoleNames()->all());
        $this->scim('PATCH', '/Groups/' . $group['id'], $this->patchBody([['op' => 'replace', 'path' => 'displayName', 'value' => 'instructors']]))->assertOk();
        $this->assertContains('tutor', $ann->refresh()->getRoleNames()->all());
    }

    public function testChangingTheMappingResyncsEverybodyAndAdminCanNeverBeMapped(): void
    {
        $this->createScimGroup('Instructors', [$this->ann['id']]);
        $ann = User::query()->findOrFail($this->ann['id']);
        $this->assertNotContains('tutor', $ann->getRoleNames()->all());

        $this->mapRoles(['Instructors' => 'tutor']);
        $this->assertContains('tutor', $ann->refresh()->getRoleNames()->all());
        $this->mapRoles([]);
        $this->assertNotContains('tutor', $ann->refresh()->getRoleNames()->all());

        $id = \Ulams\Identity\Models\IdentityProvider::query()->firstOrFail()->id;
        $admin = $this->admin();
        foreach (['admin', 'superadmin', 'Student ', '', null, ['x']] as $role) {
            $this->actingAs($admin, 'api')->putJson('/api/admin/identity/providers/' . $id, ['scim' => ['group_roles' => ['Instructors' => $role]]])->assertStatus(422);
        }
        $this->actingAs($admin, 'api')->putJson('/api/admin/identity/providers/' . $id, ['scim' => ['group_roles' => ['' => 'tutor']]])->assertStatus(422);
        $this->assertSame(['student'], $ann->refresh()->getRoleNames()->all());
        $shown = $this->actingAs($admin, 'api')->getJson('/api/admin/identity/providers/' . $id)->json('data.scim');
        $this->assertSame([], $shown['group_roles']);
    }

    public function testAnAdministratorKeepsTheirRolesWhateverTheMapping(): void
    {
        $this->mapRoles(['Instructors' => 'tutor']);
        $user = User::query()->findOrFail($this->ann['id']);
        $user->assignRole('admin');

        $this->scim('POST', '/Groups', $this->groupBody('Instructors', [$this->ann['id']]))->assertStatus(201);

        $this->assertEqualsCanonicalizing(['student', 'admin'], $user->refresh()->getRoleNames()->all());
    }
}
