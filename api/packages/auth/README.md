# Auth


## What does it do

Package for user authentication. In addition, the package includes:
- user management,
- group management,
- profile management,
- registration.

## Installing

- `composer require ulams/auth`
- `php artisan migrate`
- `php artisan db:seed --class="Ulams\Auth\Database\Seeders\AuthPermissionSeeder"`

Optional: 
- Run command `ulams:admin`. 

## Commands
- `ulams:admin` - create account with role admin

- `ulams:auth:prune-login-handoff` - delete login states and one-time codes that expired over a day ago (scheduled daily)

## Database

1. `category_user` - Table is used to store the user categories.
2. `groups` - Table for storing groups.
3. `group_user` - Table for storing groups assigned to the user.

4. `social_accounts` - provider identities linked to users (unique per `provider` and `provider_id`).
5. `login_handoff_states`, `login_handoff_codes` - state of a login that goes through another party and the
   one-time code that carries its result back to the front end (hashes only, single use, ADR 0121).

```
User 1 -> n Categories
User 1 -> n Groups
User 1 -> n SocialAccounts
```

## Login hand-off (ADR 0121)

`Ulams\Auth\Services\LoginHandoffService` is the shared primitive of every browser login that goes through
another party (social login now; OIDC and SAML reuse it):

- `startState()` stores the allow-listed return URL, the front end's S256 challenge and protocol data on the
  server and returns a signed state (HMAC with a key derived from the tenant `APP_KEY`, nonce, provider,
  host, expiry).
- `consumeState()` checks signature, expiry, provider and host and burns the row (`UPDATE ... WHERE consumed_at IS NULL`).
- `issueCode()` / `redeemCode()`: a code of 60 seconds, single use, bound to tenant host and verifier.
- `Ulams\Auth\Support\ReturnUrlPolicy` decides where a login may send the browser back to (the tenant's own
  origins, as for the origin check of the API).

Social login linking rules and the settings (`ulams_auth.social_login.trusted_domains`) are described in the
documentation site ("Social login").

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/Auth/)

## Tests

Run `./vendor/bin/phpunit` to run tests.

## Events

- `AccountBlocked` - Event is dispatched after blocking the user's account (`is_active=false`).
- `AccountConfirmed` - Event is dispatched after the user verifies the account.
- `AccountDeleted` - Event is dispatched after deleting the user.
- `AccountMustBeEnableByAdmin` - Event is dispatched when the user registers and `Config::get('ulams_auth.account_must_be_enabled_by_admin') === SettingStatusEnum::ENABLED`
- `AccountRegistered` - Event is dispatched after the account is registered.
- `ForgotPassword` - Event is dispatched when a password reset request is sent.
- `Login` - Event is dispatched on successful login.
- `Logout` - Event is dispatched after logout.
- `PasswordChanged` - Event is dispatched after the password changed.
- `ResetPassword` - Event is dispatched after resetting the password. 
- `UserAddedToGroup` - Event is dispatched after adding the user to the group.
- `UserRemovedFromGroup` - Event is dispatched after removing the user from the group.


## Listeners

- `RevokeUserTokens` - listens for `AccountBlocked` and `AccountDeleted` and revokes every access and refresh token of the user.

- `CreatePasswordResetToken` - The listener listens for the ForgotPassword event and executes the following method. 
```php
public function handle(ForgotPassword $event): void
{
    if (!is_callable(self::getRunEventForgotPassword()) || self::getRunEventForgotPassword()()) {
        $user = $event->getUser();

        $this->userRepository->update([
            'password_reset_token' => Str::random(32),
        ], $user->getKey());

        $user->refresh();

        $user->notify(new ResetPassword($user->password_reset_token, $event->getReturnUrl()));
    }
}
```

This is useful if you are using [TemplateEmail](https://github.com/EscolaLMS/Templates-Email) and you don't want to send the default e-mails.
```php
 CreatePasswordResetToken::setRunEventForgotPassword(
    function () {
        $templateRepository = app(TemplateRepositoryContract::class);
        return empty($templateRepository->findTemplateDefault(
            ForgotPassword::class,
            EmailChannel::class
        ));
    }
 );
```

- `SendEmailVerificationNotification` - The listener listens for the AccountRegistered event and executes the following method.  
```php
public function handle(Registered $event)
{
    if (!is_callable(self::getRunEventEmailVerification()) || self::getRunEventEmailVerification()()) {
        if ($event->user instanceof MustVerifyEmail && !$event->user->hasVerifiedEmail()) {
            $event->user->sendEmailVerificationNotification();
        }
    }
}
```

## How to use this on frontend

### Admin panel

**List of users**
![List of users](docs/list_of_users.png "List of users")

**Creating/editing user**
![Creating/editing user](docs/user_form.png "User form")

**User categories**
![User categories](docs/user_categories.png "User categories")

**List of groups**
![List of groups](docs/list_of_groups.png "List of groups")

**Creating/editing group**
![Creating/editing group](docs/group_form.png "Creating/editing group")

**My profile**
![My profile](docs/my_profile.png "My profile")

## Permissions

Permissions are defined in [seeder](database/seeders/AuthPermissionSeeder.php).
