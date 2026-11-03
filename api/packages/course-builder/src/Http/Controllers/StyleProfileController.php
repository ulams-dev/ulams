<?php

namespace Ulams\CourseBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\CourseBuilder\Exceptions\BuilderException;
use Ulams\CourseBuilder\Http\Controllers\Concerns\ResolvesSessions;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\StyleProfile\StyleProfileService;
use Ulams\Uploads\Exceptions\UploadRejected;

/**
 * Style profiles (ADR 0196): the cited, author-approved reading of an organisation's style guide. Anyone who may use
 * the Course Builder can list, read and propose profiles (a profile belongs to the whole academy, like a theme);
 * editing and deleting a suggestion is for its creator or an admin; approving is for admins, because an approved
 * profile shapes every course drafted afterwards. The style guide is an untrusted upload; its text never leaves the
 * extraction call.
 *
 * @OA\Get(path="/api/admin/course-builder/style-profiles", operationId="admin.course-builder.style-profiles.get", summary="Style profiles of the academy, newest first (filter with status, scopeType, scopeId)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"suggested", "approved", "superseded"})),
 *     @OA\Response(response=200, description="profiles", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="no access to the Course Builder"))
 * @OA\Post(path="/api/admin/course-builder/style-profiles", operationId="admin.course-builder.style-profiles.post", summary="Propose a style profile: upload a style guide (file, MD/PDF/DOCX) to have it read and cited, or send values by hand (profile, optionally from an existing profile id)", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary"), @OA\Property(property="name", type="string"), @OA\Property(property="profile", type="string"), @OA\Property(property="from", type="string"), @OA\Property(property="scopeType", type="string", enum={"tenant", "brief"}), @OA\Property(property="scopeId", type="string")))),
 *     @OA\Response(response=201, description="the suggested profile with what was dropped as warnings", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=422, description="rejected upload or invalid body"))
 * @OA\Get(path="/api/admin/course-builder/style-profiles/{profile}", operationId="admin.course-builder.style-profiles.by-profile.get", summary="A style profile with the passages it cites and its diff against the approved profile", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="profile", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="profile, diff, cited fragments", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=404, description="unknown"))
 * @OA\Patch(path="/api/admin/course-builder/style-profiles/{profile}", operationId="admin.course-builder.style-profiles.by-profile.patch", summary="Edit a suggested profile (values and name); invalid values are dropped and reported", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="profile", in="path", required=true, @OA\Schema(type="string")), @OA\RequestBody(@OA\JsonContent(@OA\Property(property="name", type="string"), @OA\Property(property="profile", type="object"))),
 *     @OA\Response(response=200, description="the profile", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="not the creator or an admin"), @OA\Response(response=409, description="no longer a suggestion"))
 * @OA\Post(path="/api/admin/course-builder/style-profiles/{profile}/approve", operationId="admin.course-builder.style-profiles.by-profile.approve.post", summary="Approve a suggested profile (admins); it supersedes the approved profile of its scope", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="profile", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="approved", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="not an admin"), @OA\Response(response=409, description="not a suggestion"), @OA\Response(response=422, description="empty profile"))
 * @OA\Delete(path="/api/admin/course-builder/style-profiles/{profile}", operationId="admin.course-builder.style-profiles.by-profile.delete", summary="Delete a suggested or superseded profile", tags={"Admin Course Builder"}, security={{"passport": {}}},
 *     @OA\Parameter(name="profile", in="path", required=true, @OA\Schema(type="string")), @OA\Response(response=200, description="deleted", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=409, description="approved profiles are replaced, not deleted"))
 */
class StyleProfileController extends Controller
{
    use ResolvesSessions;

    public function __construct(private readonly StyleProfileService $profiles)
    {
    }

    private static function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK'], $status);
    }

    private static function fail(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message] + $extra, $status);
    }

    private function profileFor(string $id): StyleProfile
    {
        $profile = preg_match('/^[0-9a-z]{26}$/i', $id) ? StyleProfile::query()->find(strtolower($id)) : null;

        return $profile ?? throw new NotFoundHttpException('Style profile not found.');
    }

    private static function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return method_exists($user, 'hasRole') && $user->hasRole('admin');
    }

    private function assertOwnsOrAdmin(Request $request, StyleProfile $profile): void
    {
        if ((int) $profile->created_by !== (int) $request->user()->getAuthIdentifier() && !self::isAdmin($request)) {
            throw new AccessDeniedHttpException('This style profile was proposed by someone else.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertCanUse($request);
        $request->validate(['status' => ['nullable', 'in:suggested,approved,superseded'], 'scopeType' => ['nullable', 'in:tenant,entry_point,brief'], 'scopeId' => ['nullable', 'string', 'max:64']]);
        $query = StyleProfile::query()->latest('created_at')->limit(100);
        foreach (['status' => 'status', 'scopeType' => 'scope_type', 'scopeId' => 'scope_id'] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->query($param));
            }
        }

        return self::ok($query->get()->map(fn (StyleProfile $p) => $this->profiles->present($p))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanUse($request);
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file'],
            'from' => ['nullable', 'string', 'max:26'],
            'scopeType' => ['nullable', 'in:tenant,brief,entry_point'],
            'scopeId' => ['nullable', 'regex:/^[a-z0-9][a-z0-9-]{0,63}$/'],
        ]);
        // `brief` profiles are named alternatives a Course Brief can pick (scopeId is their name); entry points come with entry points
        $scopeType = (string) $request->input('scopeType', StyleProfile::TENANT);
        if ($scopeType === 'entry_point') {
            return self::fail('Entry point profiles arrive with entry points: use scopeType tenant or brief.', 422, ['errors' => ['scopeType' => ['not available yet']]]);
        }
        $scopeId = $scopeType === StyleProfile::TENANT ? null : ((string) $request->input('scopeId') ?: (Str::slug((string) $request->input('name', '')) ?: null));
        if ($scopeType === 'brief' && $scopeId === null) {
            return self::fail('A brief profile needs a scopeId (its name) or a name.', 422, ['errors' => ['scopeId' => ['required for a brief profile']]]);
        }
        $name = $request->filled('name') ? (string) $request->input('name') : null;
        if ($request->hasFile('file')) {
            try {
                $profile = $this->profiles->createFromUpload($request->user(), $request->file('file'), $name, $scopeType, $scopeId);
            } catch (UploadRejected $e) {
                return self::fail($e->getMessage(), 422, ['errors' => ['file' => [$e->getMessage()]], 'reason' => $e->reason]);
            } catch (BuilderException $e) {
                return self::fail($e->getMessage(), $e->status);
            }

            return self::ok($this->profiles->present($profile, true), 201);
        }
        $values = $request->input('profile', []);
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        if (!is_array($values)) {
            return self::fail('`profile` must be an object.', 422, ['errors' => ['profile' => ['must be an object']]]);
        }
        $from = $request->filled('from') ? $this->profileFor((string) $request->input('from')) : null;
        if ($from === null && $values === []) {
            return self::fail('Send a style guide file, or the values of a profile.', 422, ['errors' => ['file' => ['required without profile']]]);
        }

        return self::ok($this->profiles->present($this->profiles->createManual($request->user(), $name, $values, $from, $scopeType, $scopeId), true), 201);
    }

    public function show(Request $request, string $profile): JsonResponse
    {
        $this->assertCanUse($request);

        return self::ok($this->profiles->present($this->profileFor($profile), true));
    }

    public function update(Request $request, string $profile): JsonResponse
    {
        $this->assertCanUse($request);
        $p = $this->profileFor($profile);
        $this->assertOwnsOrAdmin($request, $p);
        $request->validate(['name' => ['nullable', 'string', 'max:255'], 'profile' => ['nullable', 'array']]);
        try {
            $updated = $this->profiles->edit($p, (array) $request->input('profile', (array) $p->profile), $request->filled('name') ? (string) $request->input('name') : null);
        } catch (BuilderException $e) {
            return self::fail($e->getMessage(), $e->status);
        }

        return self::ok($this->profiles->present($updated, true));
    }

    public function approve(Request $request, string $profile): JsonResponse
    {
        $this->assertCanUse($request);
        $p = $this->profileFor($profile);
        if (!self::isAdmin($request)) {
            throw new AccessDeniedHttpException('Only an admin approves a style profile.');
        }
        try {
            $approved = $this->profiles->approve($p, $request->user());
        } catch (BuilderException $e) {
            return self::fail($e->getMessage(), $e->status);
        }

        return self::ok($this->profiles->present($approved, true));
    }

    public function destroy(Request $request, string $profile): JsonResponse
    {
        $this->assertCanUse($request);
        $p = $this->profileFor($profile);
        $this->assertOwnsOrAdmin($request, $p);
        try {
            $this->profiles->delete($p);
        } catch (BuilderException $e) {
            return self::fail($e->getMessage(), $e->status);
        }

        return self::ok(['id' => $p->id]);
    }
}
