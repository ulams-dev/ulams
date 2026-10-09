<?php

use Illuminate\Support\Facades\Route;
use Ulams\Lti\Http\Controllers\Admin\LtiAdminController;
use Ulams\Lti\Http\Controllers\FrameOriginsController;
use Ulams\Lti\Http\Controllers\JwksController;
use Ulams\Lti\Http\Controllers\Platform\AgsController;
use Ulams\Lti\Http\Controllers\Platform\NrpsController;
use Ulams\Lti\Http\Controllers\Platform\PlatformController;
use Ulams\Lti\Http\Controllers\Tool\ToolController;

// Public key set (both sides)
Route::get('api/lti/jwks', JwksController::class);
Route::get('.well-known/jwks.json', JwksController::class);

// Origins the front's CSP must allow in frame-src (public, cached)
Route::get('api/lti/frame-origins', FrameOriginsController::class);

// Admin registrations (permission lti_manage, checked in the form requests)
Route::group(['prefix' => 'api/admin/lti', 'middleware' => ['auth:api']], function () {
    Route::get('endpoints', [LtiAdminController::class, 'endpoints']);
    Route::get('tools', [LtiAdminController::class, 'tools']);
    Route::post('tools', [LtiAdminController::class, 'storeTool']);
    Route::get('tools/{tool}', [LtiAdminController::class, 'showTool'])->whereNumber('tool');
    Route::put('tools/{tool}', [LtiAdminController::class, 'updateTool'])->whereNumber('tool');
    Route::delete('tools/{tool}', [LtiAdminController::class, 'destroyTool'])->whereNumber('tool');
    Route::post('tools/{tool}/deep-link', [LtiAdminController::class, 'deepLink'])->whereNumber('tool');
    Route::get('platforms', [LtiAdminController::class, 'platforms']);
    Route::post('platforms', [LtiAdminController::class, 'storePlatform']);
    Route::get('platforms/{platform}', [LtiAdminController::class, 'showPlatform'])->whereNumber('platform');
    Route::put('platforms/{platform}', [LtiAdminController::class, 'updatePlatform'])->whereNumber('platform');
    Route::delete('platforms/{platform}', [LtiAdminController::class, 'destroyPlatform'])->whereNumber('platform');
});

// Platform side: learners launch tools; tools call back
Route::post('api/lti/launches/{topic}', [PlatformController::class, 'launch'])->whereNumber('topic')->middleware('auth:api');
Route::match(['get', 'post'], 'api/lti/platform/authorize', [PlatformController::class, 'oidcAuthorize']);
Route::post('api/lti/platform/token', [PlatformController::class, 'token']);
Route::post('api/lti/platform/deep-links', [PlatformController::class, 'deepLinks']);
Route::group(['prefix' => 'api/lti/platform/ags/{course}/lineitems', 'where' => ['course' => '[0-9]+', 'lineItem' => '[0-9]+']], function () {
    Route::get('/', [AgsController::class, 'index']);
    Route::post('/', [AgsController::class, 'store']);
    Route::get('{lineItem}', [AgsController::class, 'show']);
    Route::put('{lineItem}', [AgsController::class, 'update']);
    Route::delete('{lineItem}', [AgsController::class, 'destroy']);
    Route::post('{lineItem}/scores', [AgsController::class, 'scores']);
    Route::get('{lineItem}/results', [AgsController::class, 'results']);
});

// Names and Role Provisioning Services 2.0: the member list of a course, for tools with NRPS enabled
Route::get('api/lti/platform/nrps/{course}', [NrpsController::class, 'memberships'])->whereNumber('course');

// Tool side: platforms launch us
Route::match(['get', 'post'], 'api/lti/tool/login', [ToolController::class, 'login']);
Route::post('api/lti/tool/launch', [ToolController::class, 'launch']);
Route::post('api/lti/tool/deep-link', [ToolController::class, 'deepLink']);
Route::post('api/lti/tool/exchange', [ToolController::class, 'exchange'])->middleware('throttle:30,1');
