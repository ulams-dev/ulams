<?php

namespace Ulams\Identity\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Auth\Models\User;
use Ulams\Identity\Models\IdentityAudit;
use Ulams\Identity\Models\IdentityLink;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Tests\Support\RunsOidcLogin;
use Ulams\Identity\Tests\TestCase;

/** Who an identity may become: a new student, a trusted link, or a refusal (plan 4.5, ADR 0121). */
class AccountLinkingTest extends TestCase
{
    use RunsOidcLogin;

    private IdentityProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = $this->provider();
    }

    /** Signs in and returns the coarse outcome the browser sees: 'code' or the error reason. */
    private function outcome(array $claims = [], array $options = []): string
    {
        $this->newVerifier();
        $params = $this->queryOf((string) $this->signIn($claims, $options)['response']->headers->get('Location'));

        return isset($params['code']) ? 'code' : (string) ($params['error'] ?? '');
    }

    private function lastReason(): string
    {
        return IdentityAudit::query()->where('action', 'sso.login_failed')->latest('id')->firstOrFail()->data['reason'];
    }

    private function localUser(array $attributes = [], ?string $role = 'student', bool $verified = true): User
    {
        $user = $role === 'admin' ? $this->admin($attributes) : ($role === 'tutor' ? $this->tutor($attributes) : $this->student($attributes));
        $user->forceFill(['email' => 'ada@example.com', 'email_verified_at' => $verified ? now() : null, 'is_active' => true])->save();

        return $user->refresh();
    }

    public function testAnExistingAccountIsNotLinkedByEmailWithoutTrust(): void
    {
        $user = $this->localUser();

        $this->assertSame('link_required', $this->outcome());

        $this->assertSame('not_trusted_for_domain', $this->lastReason());
        $this->assertSame(0, IdentityLink::query()->count());
        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
        $this->assertNotNull($user->refresh()->email);
    }

    public function testATrustedProviderLinksAnExistingStudentWhoseAddressItVouchesFor(): void
    {
        $this->provider->forceFill(['trust_email' => true])->save();
        $user = $this->localUser();

        $this->assertSame('code', $this->outcome());

        $link = IdentityLink::query()->firstOrFail();
        $this->assertSame([$user->getKey(), 'email_trust', 'subject-1'], [$link->user_id, $link->linked_via, $link->subject]);
        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
        $this->assertTrue(IdentityAudit::query()->where('action', 'sso.link')->exists());
    }

    public function testTrustNeedsTheProviderToSayTheAddressIsVerified(): void
    {
        $this->provider->forceFill(['trust_email' => true])->save();
        $this->localUser();

        $this->assertSame('link_required', $this->outcome(['email_verified' => false]));
        $this->assertSame('email_not_verified_by_provider', $this->lastReason());
        $this->assertSame('link_required', $this->outcome(['email_verified' => null]));
        // a string is not a verification
        $this->assertSame('link_required', $this->outcome(['email_verified' => 'yes']));
        $this->assertSame(0, IdentityLink::query()->count());
    }

    public function testTrustOnlyCoversTheDomainsOfTheOrganisation(): void
    {
        $this->provider->forceFill(['trust_email' => true, 'email_domains' => ['corp.example']])->save();
        $this->localUser();

        $this->assertSame('link_required', $this->outcome());
        $this->assertSame('not_trusted_for_domain', $this->lastReason());
    }

    public function testAdminsTutorsAndSuperadminsAreNeverLinkedByEmail(): void
    {
        $this->provider->forceFill(['trust_email' => true])->save();

        $admin = $this->localUser([], 'admin');
        $this->assertSame('link_required', $this->outcome());
        $admin->forceDelete();

        $tutor = $this->localUser([], 'tutor');
        $this->assertSame('link_required', $this->outcome());
        $tutor->forceDelete();

        config(['ulams_auth.superadmins' => ['ada@example.com']]);
        $this->localUser();
        $this->assertSame('link_required', $this->outcome());

        $this->assertSame(0, IdentityLink::query()->count());
    }

    public function testClaimingAnUnconfirmedAccountDropsItsPasswordAndTokens(): void
    {
        Event::fake([AccountConfirmed::class]);
        $this->provider->forceFill(['trust_email' => true])->save();
        // somebody registered the address with a password of their own and never confirmed it
        $squatter = $this->localUser(['password' => bcrypt('squatter-password')], 'student', false);
        $token = $squatter->createToken('squatter')->token;

        $this->assertSame('code', $this->outcome());

        $squatter->refresh();
        $this->assertNull($squatter->password);
        $this->assertTrue($squatter->hasVerifiedEmail());
        $this->assertTrue((bool) $token->fresh()->revoked);
        Event::assertDispatched(AccountConfirmed::class);
    }

    public function testInactiveUsersAreRefusedByEveryRoute(): void
    {
        $this->provider->forceFill(['trust_email' => true])->save();
        $user = $this->localUser();
        $user->forceFill(['is_active' => false])->save();

        // by e-mail
        $this->assertSame('account_unavailable', $this->outcome());
        $this->assertSame('user_inactive', $this->lastReason());

        // by an existing link
        IdentityLink::query()->create(['provider_id' => $this->provider->getKey(), 'user_id' => $user->getKey(), 'subject' => 'subject-1', 'linked_via' => 'admin']);
        $this->assertSame('account_unavailable', $this->outcome(['email' => 'other@example.com']));
    }

    public function testDeletedUsersAreRefusedAndTheirAddressIsNotReused(): void
    {
        $user = $this->localUser();
        // a soft delete that kept the address (account deletion masks it; an admin delete does not)
        User::query()->whereKey($user->getKey())->update(['deleted_at' => now()]);

        $this->assertSame('account_unavailable', $this->outcome());
        $this->assertSame('user_deleted', $this->lastReason());
        $this->assertSame(1, User::withTrashed()->where('email', 'ada@example.com')->count());
    }

    public function testAnAddressThatDiffersOnlyInCaseIsTheSameAddress(): void
    {
        $this->localUser();

        $this->assertSame('link_required', $this->outcome(['email' => 'ADA@Example.com']));
        $this->assertSame(1, User::withTrashed()->whereRaw('LOWER(email) = ?', ['ada@example.com'])->count());
    }

    public function testNothingIsCreatedForAnAddressTheProviderDoesNotVouchFor(): void
    {
        $this->assertSame('email_unverified', $this->outcome(['email_verified' => false]));

        $this->assertFalse(User::query()->where('email', 'ada@example.com')->exists());
        $this->assertSame('email_not_verified_by_provider', $this->lastReason());
    }

    public function testATrustedProviderMayCreateAccountsForAddressesItDoesNotFlag(): void
    {
        // Entra ID and other business providers send no email_verified claim
        $this->provider->forceFill(['trust_email' => true])->save();

        $this->assertSame('code', $this->outcome(['email_verified' => null]));

        $this->assertTrue(User::query()->where('email', 'ada@example.com')->firstOrFail()->hasVerifiedEmail());
    }

    public function testNoEmailMeansNoAccount(): void
    {
        $this->assertSame('email_required', $this->outcome(['email' => null]));
        $this->assertSame('email_required', $this->outcome(['email' => 'not-an-address']));
        $this->assertSame(0, User::query()->count());
    }

    public function testAProviderWithSignUpSwitchedOffCreatesNobody(): void
    {
        $this->provider->forceFill(['jit_enabled' => false])->save();

        $this->assertSame('signup_disabled', $this->outcome());
        $this->assertSame(0, User::query()->count());
    }

    public function testASignUpWithoutAVerifiedAddressNeverTouchesOtherAccounts(): void
    {
        $other = $this->student(['email' => 'victim@example.com']);

        $this->outcome(['email' => 'victim@example.com', 'email_verified' => false]);

        $this->assertSame(1, User::query()->where('email', 'victim@example.com')->count());
        $this->assertSame($other->getKey(), User::query()->where('email', 'victim@example.com')->first()->getKey());
        $this->assertSame(0, IdentityLink::query()->count());
    }

    public function testNamesAreCleanedAndHaveFallbacks(): void
    {
        $this->outcome(['given_name' => null, 'family_name' => null, 'name' => "Grace\x00 Brewster Hopper", 'sub' => 's1', 'email' => 'grace@example.com']);
        $this->outcome(['given_name' => null, 'family_name' => null, 'name' => null, 'sub' => 's2', 'email' => 'linus@example.com']);

        $grace = User::query()->where('email', 'grace@example.com')->firstOrFail();
        $this->assertSame(['Grace', 'Brewster Hopper'], [$grace->first_name, $grace->last_name]);
        $this->assertSame('linus', User::query()->where('email', 'linus@example.com')->firstOrFail()->first_name);
    }

    public function testTheTutorRoleCanBeTheDefaultOfAProvider(): void
    {
        $this->provider->forceFill(['default_role' => 'tutor'])->save();

        $this->assertSame('code', $this->outcome());

        $this->assertSame(['tutor'], User::query()->where('email', 'ada@example.com')->firstOrFail()->getRoleNames()->all());
    }

    public function testTheSameSubjectAtAnotherProviderIsAnotherIdentity(): void
    {
        $this->provider(['slug' => 'second', 'name' => 'Second']);
        $this->outcome();

        $this->newVerifier();
        $params = $this->queryOf((string) $this->signIn([], ['slug' => 'second'])['response']->headers->get('Location'));

        // same subject and address, but the first provider's link does not carry over and trust is off
        $this->assertSame('link_required', $params['error']);
    }

    public function testEveryRefusalIsAuditedWithoutPersonalData(): void
    {
        $this->localUser();
        $this->outcome();

        $row = IdentityAudit::query()->where('action', 'sso.login_failed')->firstOrFail();
        $this->assertSame('idp', $row->actor);
        $this->assertStringNotContainsString('ada@example.com', json_encode($row->data));
        $this->assertSame(['reason', 'refused', 'slug'], collect(array_keys($row->data))->sort()->values()->all());
    }
}
