<?php

namespace Ulams\Identity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\Identity\Http\Controllers\Scim\ScimUsersController;
use Ulams\Identity\Services\Scim\PatchApplier;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Services\Scim\ScimError;

class ScimPatchApplierTest extends TestCase
{
    private function user(): array
    {
        return [
            'schemas' => [Schemas::USER],
            'id' => '7',
            'userName' => 'ada@example.com',
            'name' => ['givenName' => 'Ada', 'familyName' => 'Lovelace'],
            'emails' => [['value' => 'ada@example.com', 'type' => 'work', 'primary' => true]],
            'active' => true,
        ];
    }

    private function patch(array $user, array $operations): array
    {
        return ScimUsersController::patcher()->apply($user, ['schemas' => [PatchApplier::SCHEMA], 'Operations' => $operations]);
    }

    private function members(): PatchApplier
    {
        return new PatchApplier(['members'], ['displayName', 'members', 'value'], [Schemas::GROUP]);
    }

    public function testReplaceASimpleAttribute(): void
    {
        $out = $this->patch($this->user(), [['op' => 'replace', 'path' => 'userName', 'value' => 'ada.l@example.com']]);

        $this->assertSame('ada.l@example.com', $out['userName']);
    }

    public function testTheOpIsCaseInsensitiveAsEntraSendsIt(): void
    {
        $out = $this->patch($this->user(), [['op' => 'Replace', 'path' => 'active', 'value' => 'False'], ['op' => 'Add', 'path' => 'title', 'value' => 'Nurse']]);

        $this->assertSame('False', $out['active']); // normalised to a boolean by the mapper, not here
        $this->assertSame('Nurse', $out['title']);
    }

    public function testReplaceWithoutAPathTakesAnObjectOfAttributes(): void
    {
        $out = $this->patch($this->user(), [['op' => 'replace', 'value' => ['active' => false, 'name' => ['givenName' => 'Augusta', 'familyName' => 'King']]]]);

        $this->assertFalse($out['active']);
        $this->assertSame(['givenName' => 'Augusta', 'familyName' => 'King'], $out['name']);
    }

    public function testReadOnlyKeysEchoedWithoutAPathAreIgnoredButNotWithOne(): void
    {
        $out = $this->patch($this->user(), [['op' => 'replace', 'value' => ['id' => '999', 'meta' => ['x' => 1], 'title' => 'T']]]);
        $this->assertSame('7', $out['id']);
        $this->assertSame('T', $out['title']);

        $this->expectException(ScimError::class);
        $this->patch($this->user(), [['op' => 'replace', 'path' => 'id', 'value' => '9']]);
    }

    public function testASubAttributePath(): void
    {
        $out = $this->patch($this->user(), [['op' => 'replace', 'path' => 'name.givenName', 'value' => 'Augusta'], ['op' => 'add', 'path' => 'name.formatted', 'value' => 'x']]);

        $this->assertSame('Augusta', $out['name']['givenName']);
        $this->assertSame('Lovelace', $out['name']['familyName']);
    }

    public function testAValuePathReplacesTheMatchingElementsSubAttribute(): void
    {
        $out = $this->patch($this->user(), [['op' => 'replace', 'path' => 'emails[type eq "work"].value', 'value' => 'new@example.com']]);

        $this->assertSame('new@example.com', $out['emails'][0]['value']);
    }

    public function testAValuePathWithNoMatchIsNoTarget(): void
    {
        try {
            $this->patch($this->user(), [['op' => 'replace', 'path' => 'emails[type eq "home"].value', 'value' => 'x@example.com']]);
            $this->fail('expected noTarget');
        } catch (ScimError $e) {
            $this->assertSame('noTarget', $e->scimType);
        }
    }

    public function testRemoveNeedsAPath(): void
    {
        try {
            $this->patch($this->user(), [['op' => 'remove']]);
            $this->fail('expected noTarget');
        } catch (ScimError $e) {
            $this->assertSame('noTarget', $e->scimType);
        }
    }

    public function testRemoveAnAttributeAndAFilteredValue(): void
    {
        $user = $this->user();
        $user['title'] = 'Nurse';
        $user['emails'][] = ['value' => 'home@example.com', 'type' => 'home'];

        $out = $this->patch($user, [['op' => 'remove', 'path' => 'title'], ['op' => 'remove', 'path' => 'emails[type eq "home"]']]);

        $this->assertArrayNotHasKey('title', $out);
        $this->assertCount(1, $out['emails']);
    }

    public function testEnterpriseExtensionByPathWithTheUrnPrefix(): void
    {
        $out = $this->patch($this->user(), [
            ['op' => 'add', 'path' => Schemas::ENTERPRISE . ':department', 'value' => 'ICU'],
            ['op' => 'replace', 'path' => Schemas::ENTERPRISE . ':manager', 'value' => ['value' => '3']],
        ]);

        $this->assertSame('ICU', $out[Schemas::ENTERPRISE]['department']);
        $this->assertSame(['value' => '3'], $out[Schemas::ENTERPRISE]['manager']);

        $out = $this->patch($out, [['op' => 'remove', 'path' => Schemas::ENTERPRISE . ':department']]);
        $this->assertArrayNotHasKey('department', $out[Schemas::ENTERPRISE]);
    }

    public function testEnterpriseExtensionAsAKeyOfAPathlessValue(): void
    {
        $out = $this->patch($this->user(), [['op' => 'add', 'value' => [Schemas::ENTERPRISE => ['department' => 'ICU', 'costCenter' => '4130']]]]);

        $this->assertSame(['department' => 'ICU', 'costCenter' => '4130'], $out[Schemas::ENTERPRISE]);
    }

    public function testDottedKeysInAPathlessValueAreTreatedAsPaths(): void
    {
        $out = $this->patch($this->user(), [['op' => 'replace', 'value' => ['name.givenName' => 'Augusta']]]);

        $this->assertSame('Augusta', $out['name']['givenName']);
    }

    public function testAddingMembersAppendsAndDoesNotDuplicate(): void
    {
        $group = ['id' => '1', 'displayName' => 'Nurses', 'members' => [['value' => '5']]];

        $out = $this->members()->apply($group, ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['op' => 'add', 'path' => 'members', 'value' => [['value' => '5'], ['value' => '6']]]]]);

        $this->assertSame([['value' => '5'], ['value' => '6']], $out['members']);
    }

    public function testRemovingAMemberByValuePathOktaStyle(): void
    {
        $group = ['id' => '1', 'displayName' => 'Nurses', 'members' => [['value' => '5'], ['value' => '6']]];

        $out = $this->members()->apply($group, ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['op' => 'remove', 'path' => 'members[value eq "5"]']]]);

        $this->assertSame([['value' => '6']], $out['members']);
    }

    public function testRemovingMembersWithTheListAsValueEntraStyle(): void
    {
        $group = ['id' => '1', 'displayName' => 'Nurses', 'members' => [['value' => '5'], ['value' => '6'], ['value' => '7']]];

        $out = $this->members()->apply($group, ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['op' => 'Remove', 'path' => 'members', 'value' => [['value' => '5'], ['value' => '7']]]]]);

        $this->assertSame([['value' => '6']], $out['members']);
    }

    public function testRemovingAllMembersAndReplacingThem(): void
    {
        $group = ['id' => '1', 'displayName' => 'Nurses', 'members' => [['value' => '5']]];
        $schemas = ['schemas' => [PatchApplier::SCHEMA]];

        $this->assertSame([], $this->members()->apply($group, $schemas + ['Operations' => [['op' => 'remove', 'path' => 'members']]])['members'] ?? []);
        $this->assertSame([['value' => '9']], $this->members()->apply($group, $schemas + ['Operations' => [['op' => 'replace', 'path' => 'members', 'value' => [['value' => '9']]]]])['members']);
    }

    public function testOperationsApplyInOrderAndAFailingOneFailsTheWholeRequest(): void
    {
        $user = $this->user();
        try {
            $this->patch($user, [['op' => 'replace', 'path' => 'title', 'value' => 'A'], ['op' => 'remove', 'path' => 'emails[type eq "home"]'], ['op' => 'replace', 'path' => 'emails[type eq "none"].value', 'value' => 'x']]);
            $this->fail('expected an error');
        } catch (ScimError $e) {
            $this->assertSame('noTarget', $e->scimType);
        }
        $this->assertArrayNotHasKey('title', $user);
    }

    public function testMalformedRequestsAreRefused(): void
    {
        $cases = [
            ['schemas' => ['x'], 'Operations' => [['op' => 'add', 'path' => 'title', 'value' => 'x']]],
            ['schemas' => [PatchApplier::SCHEMA]],
            ['schemas' => [PatchApplier::SCHEMA], 'Operations' => []],
            ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['op' => 'move', 'path' => 'title']]],
            ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['path' => 'title']]],
            ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['op' => 'add', 'path' => 'a b c', 'value' => 'x']]],
            ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['op' => 'add', 'path' => 'emails[type eq', 'value' => 'x']]],
            ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['op' => 'add', 'value' => 'not an object']]],
            ['schemas' => [PatchApplier::SCHEMA], 'Operations' => [['op' => 'add', 'path' => 'title.x.y', 'value' => 'x']]],
        ];
        foreach ($cases as $i => $body) {
            try {
                ScimUsersController::patcher()->apply($this->user(), $body);
                $this->fail("case {$i} should fail");
            } catch (ScimError $e) {
                $this->assertContains($e->scimType, ['invalidSyntax', 'invalidPath', 'invalidValue'], "case {$i}");
            }
        }
    }
}
