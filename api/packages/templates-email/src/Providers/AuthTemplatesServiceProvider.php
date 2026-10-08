<?php

namespace EscolaLms\TemplatesEmail\Providers;

use EscolaLms\Auth\Events\AccountConfirmed;
use EscolaLms\Auth\Events\AccountDeletionRequested;
use EscolaLms\Auth\Events\AccountMustBeEnableByAdmin;
use EscolaLms\Auth\Events\AccountRegistered;
use EscolaLms\Auth\Events\ForgotPassword;
use EscolaLms\Auth\Events\PasswordChanged;
use EscolaLms\Auth\Events\UserAddedToGroup;
use EscolaLms\Auth\Events\UserRemovedFromGroup;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\TemplatesEmail\Auth\AccountConfirmedVariables;
use EscolaLms\TemplatesEmail\Auth\AccountDeletionRequestedVariables;
use EscolaLms\TemplatesEmail\Auth\PasswordChangedVariables;
use EscolaLms\Auth\Events\AccountBlocked;
use EscolaLms\Auth\Events\AccountDeleted;
use EscolaLms\TemplatesEmail\Auth\AccountBlockedVariables;
use EscolaLms\TemplatesEmail\Auth\AccountDeletedVariables;
use EscolaLms\TemplatesEmail\Auth\ResetPasswordVariables;
use EscolaLms\TemplatesEmail\Auth\UserAddedToGroupVariables;
use EscolaLms\TemplatesEmail\Auth\UserRemovedFromGroupVariables;
use EscolaLms\TemplatesEmail\Auth\VerifyEmailVariables;
use EscolaLms\TemplatesEmail\Auth\VerifyUserAccountVariables;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
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
