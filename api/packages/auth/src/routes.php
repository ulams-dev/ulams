<?php

use Ulams\Auth\Http\Controllers\Admin\TokenAdminController;
use Ulams\Auth\Http\Controllers\Admin\UserController;
use Ulams\Auth\Http\Controllers\Admin\UserGroupsController;
use Ulams\Auth\Http\Controllers\Admin\UserInterestsController;
use Ulams\Auth\Http\Controllers\Admin\UserSettingsController;
use Ulams\Auth\Http\Controllers\AuthApiController;
use Ulams\Auth\Http\Controllers\LoginApiController;
use Ulams\Auth\Http\Controllers\LogoutApiController;
use Ulams\Auth\Http\Controllers\ProfileAPIController;
use Ulams\Auth\Http\Controllers\MetaController;
use Ulams\Auth\Http\Controllers\RegisterApiController;
use Ulams\Auth\Http\Controllers\TokenController;
use Ulams\Auth\Http\Middleware\RegistrationEnabled;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {
    Route::get('meta', [MetaController::class, 'show'])->middleware('throttle:60,1')->name('meta');

    Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
        Route::get('auth/tokens', [TokenController::class, 'index']);
        Route::post('auth/tokens', [TokenController::class, 'store']);
        Route::get('auth/tokens/current', [TokenController::class, 'current']);
        Route::delete('auth/tokens/{id}', [TokenController::class, 'destroy']);

        Route::get('admin/tokens', [TokenAdminController::class, 'index']);
        Route::delete('admin/tokens/{id}', [TokenAdminController::class, 'destroy']);
        Route::get('admin/tokens/{id}/audit', [TokenAdminController::class, 'audit']);
        Route::get('admin/agent-audit', [TokenAdminController::class, 'agentAudit']);
    });

    Route::prefix('admin/auth')->group(function () {
        Route::post('/impersonate', [LoginApiController::class, 'impersonate'])->middleware(['auth:api'])->name('impersonate');
    });

    Route::group(['prefix' => 'auth'], function () {
        Route::post('register', [RegisterApiController::class, 'register'])->name('register.api')->middleware(RegistrationEnabled::class);
        Route::post('login', [LoginApiController::class, 'login'])->name('login.api');

        Route::get('registerable-groups', [AuthApiController::class, 'registerableGroups']);

        Route::group(['prefix' => 'password'], function () {
            Route::post('forgot', [AuthApiController::class, 'forgotPassword']);
            Route::post('reset', [AuthApiController::class, 'resetPassword'])->name('password.reset');
        });

        Route::group(['prefix' => 'social'], function () {
            Route::get('{provider}', [AuthApiController::class, 'socialRedirect']);
            Route::get('{provider}/callback', [AuthApiController::class, 'socialCallback']);
            Route::post('complete/{token}', [AuthApiController::class, 'completeSocialData']);
        });

        Route::group(['prefix' => 'email'], function () {
            Route::get('verify/{id}/{hash}', [AuthApiController::class, 'verifyEmail'])->name('verification.verify');
            Route::post('resend', [AuthApiController::class, 'resendEmailVerification'])->middleware(['auth:api', 'throttle:6,1'])->name('verification.send');
        });

        Route::group(['middleware' => ['auth:api']], function () {
            Route::post('logout', [LogoutApiController::class, 'logout'])->name('logout.api');
            Route::get('refresh', [AuthApiController::class, 'refresh']);
        });
    });
    Route::middleware(['auth:api'])->prefix('profile')->group(function () {
        Route::get('/me', [ProfileAPIController::class, 'me']);
        Route::put('/me', [ProfileAPIController::class, 'update']);
        Route::put('/me-auth', [ProfileAPIController::class, 'updateAuthData']);
        Route::put('/password', [ProfileAPIController::class, 'updatePassword']);
        Route::put('/interests', [ProfileAPIController::class, 'interests']);
        Route::get('/settings', [ProfileAPIController::class, 'settings']);
        Route::put('/settings', [ProfileAPIController::class, 'settingsUpdate']);
        Route::post('/upload-avatar', [ProfileAPIController::class, 'uploadAvatar']);
        Route::delete('/delete-avatar', [ProfileAPIController::class, 'deleteAvatar']);
        Route::delete('', [ProfileAPIController::class, 'delete']);
        Route::post('/delete/init', [ProfileAPIController::class, 'initProfileDeletion']);
    });

    Route::get('/profile/delete/{userId}/{token}', [ProfileAPIController::class, 'confirmDeletionProfile'])
        ->whereNumber('userId')
        ->name('profile.delete.confirmation');

    Route::middleware(['auth:api'])->prefix('admin')->group(function () {
        Route::group(['prefix' => 'users'], function () {
            // Users
            Route::get('/', [UserController::class, 'listUsers']);
            Route::post('/', [UserController::class, 'createUser']);
            // Single User
            Route::get('/{id}', [UserController::class, 'getUser']);
            Route::patch('/{id}', [UserController::class, 'partialUpdateUser']);
            Route::put('/{id}', [UserController::class, 'updateUser']);
            Route::delete('/{id}', [UserController::class, 'deleteUser']);
            // Avatar
            Route::post('/{id}/avatar', [UserController::class, 'uploadAvatar']);
            Route::delete('/{id}/avatar', [UserController::class, 'deleteAvatar']);
            // Settings
            Route::get('/{id}/settings', [UserSettingsController::class, 'listUserSettings']);
            Route::patch('/{id}/settings', [UserSettingsController::class, 'patchUserSettings']);
            Route::put('/{id}/settings', [UserSettingsController::class, 'putUserSettings']);
            // Interests
            Route::get('/{id}/interests', [UserInterestsController::class, 'listUserInterests']);
            Route::put('/{id}/interests', [UserInterestsController::class, 'updateUserInterests']);
            // Single Interest
            Route::post('/{id}/interests', [UserInterestsController::class, 'addUserInterest']);
            Route::delete('/{id}/interests/{interest_id}', [UserInterestsController::class, 'deleteUserInterest']);
        });

        Route::group(['prefix' => 'user-groups'], function () {
            Route::get('/', [UserGroupsController::class, 'listGroups']);
            Route::get('/tree', [UserGroupsController::class, 'listGroupsTree']);
            Route::get('/users', [UserGroupsController::class, 'listWithUsers']);
            Route::post('/', [UserGroupsController::class, 'createGroup']);
            Route::get('/{id}', [UserGroupsController::class, 'getGroup']);
            Route::put('/{id}', [UserGroupsController::class, 'updateGroup']);
            Route::patch('/{id}', [UserGroupsController::class, 'updateGroup']);
            Route::delete('/{id}', [UserGroupsController::class, 'deleteGroup']);
            //
            Route::post('/{id}/members/', [UserGroupsController::class, 'addMember']);
            Route::delete('/{id}/members/{user_id}', [UserGroupsController::class, 'removeMember']);
        });
    });
});
