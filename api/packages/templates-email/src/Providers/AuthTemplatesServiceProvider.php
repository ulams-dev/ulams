<?php

namespace Ulams\TemplatesEmail\Providers;

use Ulams\Auth\Events\AccountConfirmed;
use Ulams\Auth\Events\AccountDeletionRequested;
use Ulams\Auth\Events\AccountMustBeEnableByAdmin;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Events\ForgotPassword;
use Ulams\Auth\Events\PasswordChanged;
use Ulams\Auth\Events\UserAddedToGroup;
use Ulams\Auth\Events\UserRemovedFromGroup;
use Ulams\Templates\Facades\Template;
use Ulams\TemplatesEmail\Auth\AccountConfirmedVariables;
use Ulams\TemplatesEmail\Auth\AccountDeletionRequestedVariables;
use Ulams\TemplatesEmail\Auth\PasswordChangedVariables;
use Ulams\Auth\Events\AccountBlocked;
use Ulams\Auth\Events\AccountDeleted;
use Ulams\TemplatesEmail\Auth\AccountBlockedVariables;
use Ulams\TemplatesEmail\Auth\AccountDeletedVariables;
use Ulams\TemplatesEmail\Auth\ResetPasswordVariables;
use Ulams\TemplatesEmail\Auth\UserAddedToGroupVariables;
use Ulams\TemplatesEmail\Auth\UserRemovedFromGroupVariables;
use Ulams\TemplatesEmail\Auth\VerifyEmailVariables;
use Ulams\TemplatesEmail\Auth\VerifyUserAccountVariables;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Illuminate\Support\ServiceProvider;

class AuthTemplatesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Template::register(AccountMustBeEnableByAdmin::class, EmailChannel::class, VerifyUserAccountVariables::class);
        Template::register(AccountConfirmed::class, EmailChannel::class, AccountConfirmedVariables::class);
        Template::register(UserAddedToGroup::class, EmailChannel::class, UserAddedToGroupVariables::class);
        Template::register(UserRemovedFromGroup::class, EmailChannel::class, UserRemovedFromGroupVariables::class);
        Template::register(PasswordChanged::class, EmailChannel::class, PasswordChangedVariables::class);
        Template::register(AccountMustBeEnableByAdmin::class, EmailChannel::class, VerifyUserAccountVariables::class);
        Template::register(ForgotPassword::class, EmailChannel::class, ResetPasswordVariables::class);
        Template::register(AccountRegistered::class, EmailChannel::class, VerifyEmailVariables::class);
        Template::register(AccountDeleted::class, EmailChannel::class, AccountDeletedVariables::class);
        Template::register(AccountBlocked::class, EmailChannel::class, AccountBlockedVariables::class);
        Template::register(AccountDeletionRequested::class, EmailChannel::class, AccountDeletionRequestedVariables::class);
    }
}
