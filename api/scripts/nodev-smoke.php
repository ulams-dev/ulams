<?php

/**
 * Production-like smoke test: run it against `composer install --no-dev` and a migrated database.
 *
 * Boots the app and drives a Course Builder session (upload, interview, outline, generation, apply) over
 * the real admin API routes on the fake AI driver, then records a failed job. Anything that needs a dev-only
 * class (model factories, faker generators, testing helpers) at runtime fails here instead of in production.
 *
 * Needs: AI_DRIVER=fake, QUEUE_CONNECTION=sync, a migrated DB seeded with PermissionsSeeder and
 * CourseBuilderPermissionSeeder, and a personal access client (passport:client --personal).
 */

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Ulams\Core\Enums\UserRole;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Models\Course;
use Ulams\Pages\Models\Page;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (class_exists(\DavidBadura\FakerMarkdownGenerator\FakerProvider::class)) {
    fwrite(STDERR, "FAIL: dev packages are installed; the smoke test is only meaningful with --no-dev\n");
    exit(1);
}

config([
    'ai.driver' => 'fake',
    'ai.fake.mode' => 'synthetic',
    'ai.limits.tenant_monthly_usd' => 1000000,
    'queue.default' => 'sync',
    'course_builder.queue_connection' => 'sync',
    'course_builder.sse.max_seconds' => 0,
]);
Storage::fake('course_builder_private');

$step = function (string $name): void {
    fwrite(STDOUT, "ok  $name\n");
};
$fail = function (string $message): never {
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
};

$userModel = config('auth.providers.users.model');
$admin = new $userModel([
    'first_name' => 'Smoke',
    'last_name' => 'Admin',
    'email' => 'smoke-' . Str::random(8) . '@example.test',
    'password' => bcrypt(Str::random(24)),
    'email_verified_at' => now(),
    'is_active' => true,
]);
$admin->save();
$admin->assignRole(UserRole::ADMIN);
Passport::actingAs($admin, [], 'api');
$step('admin user created without factories');

$call = function (string $method, string $uri, array $json = [], array $files = []) use ($app, $fail) {
    $server = $files ? [] : ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    $request = Request::create($uri, $method, $files ? [] : [], [], $files, $server + ['HTTP_ACCEPT' => 'application/json'], $files || !$json ? null : json_encode($json));
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
    if ($response->getStatusCode() >= 400) {
        $fail("$method $uri returned {$response->getStatusCode()}: " . substr((string) $response->getContent(), 0, 600));
    }

    return json_decode((string) $response->getContent(), true) ?? [];
};
$action = fn (Session $s, string $name, string $surface, array $context = []) => $call('POST', "/api/admin/course-builder/sessions/{$s->id}/runs", [
    'threadId' => $s->id,
    'runId' => 'smoke',
    'messages' => [],
    'forwardedProps' => ['action' => ['name' => $name, 'surfaceId' => $surface, 'sourceComponentId' => 'x', 'context' => $context]],
]);

$sessionId = $call('POST', '/api/admin/course-builder/sessions')['data']['session']['id'];
$session = Session::query()->findOrFail($sessionId);
$fixture = __DIR__ . '/../packages/course-builder/resources/fixtures/coffee-brewing.md';
$copy = tempnam(sys_get_temp_dir(), 'smoke') . '-coffee-brewing.md';
copy($fixture, $copy);
$call('POST', "/api/admin/course-builder/sessions/{$session->id}/sources", [], ['file' => new UploadedFile($copy, 'coffee-brewing.md', null, null, true)]);
$session->refresh();
$session->status === Session::INTERVIEWING || $fail("expected interviewing, got {$session->status}");
$step('upload and interview');

$action($session, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 30, 'lessonMinutes' => 10]]);
$action($session, 'answer', 'interview', ['key' => 'assessments', 'value' => ['quiz', 'final']]);
$action($session, 'decide_for_me', 'interview');
$session->refresh();
$session->status === Session::OUTLINE_REVIEW || $fail("expected outline review, got {$session->status}");
$step('outline proposed');

$outline = $session->current_version_id;
$action($session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline]);
$session->refresh();
$session->status === Session::APPLY_REVIEW || $fail("expected apply review, got {$session->status}");
$step('content generated');

$version = $session->current_version_id;
$action($session, 'approve_apply', "apply-{$version}", ['versionId' => $version]);
$session->refresh();
$session->status === Session::APPLIED || $fail("expected applied, got {$session->status}");
$course = Course::query()->find($session->course_id) ?? $fail('the applied course does not exist');
Page::query()->where('slug', "course-{$course->getKey()}")->where('active', false)->exists() || $fail('the inactive landing page was not created');
$step('apply created the course and the inactive landing page');

// A failed job must be recordable (failed_jobs.uuid is NOT NULL).
$uuid = (string) Str::uuid();
$app->make('queue.failer')->log('sync', 'default', json_encode(['uuid' => $uuid]), new RuntimeException('smoke'));
DB::table('failed_jobs')->where('uuid', $uuid)->exists() || $fail('failed job was not recorded with its uuid');
DB::table('failed_jobs')->where('uuid', $uuid)->delete();
$step('failed job recorded with a uuid');

fwrite(STDOUT, "No-dev smoke test passed.\n");
