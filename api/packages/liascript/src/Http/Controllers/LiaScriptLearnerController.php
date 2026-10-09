<?php

namespace Ulams\LiaScript\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use Ulams\LiaScript\Models\LiaScriptTopic;
use Ulams\LiaScript\Services\LiaScriptPlayer;
use Ulams\LiaScript\Services\ProgressToken;

/**
 * Learners play a LiaScript topic on the tenant content origin and the player reports progress.
 *
 * @OA\Post(path="/api/liascript/launches/{topic}", summary="Start a LiaScript topic on the tenant content origin", tags={"LiaScript"}, security={{"passport": {}}},
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="{url, sections, version}; url carries a topic-scoped progress token in its fragment"),
 *     @OA\Response(response=401, description="endpoint requires authentication"), @OA\Response(response=403, description="no access to the course"),
 *     @OA\Response(response=404, description="not a LiaScript topic"), @OA\Response(response=503, description="no content origin or player not installed"))
 * @OA\Post(path="/api/liascript/progress/{topic}", summary="Progress reported by the content-origin player", tags={"LiaScript"},
 *     description="Authenticated with the progress token (X-Ulams-Tracking-Token). The topic is complete when the learner reaches the last section, or LiaScript reports completed/passed.",
 *     @OA\Parameter(name="topic", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="location", type="integer"), @OA\Property(property="status", type="string"))),
 *     @OA\Response(response=200, description="{status: in_progress|complete}"), @OA\Response(response=401, description="invalid or expired token"))
 */
class LiaScriptLearnerController extends Controller
{
    public const TOKEN_HEADER = 'X-Ulams-Tracking-Token';

    public function __construct(
        private readonly LiaScriptPlayer $player,
        private readonly CourseProgressRepositoryContract $progress,
    ) {
    }

    public function launch(Request $request, int $topic): JsonResponse
    {
        /** @var Topic|null $model */
        $model = Topic::query()->with(['topicable', 'lesson.course'])->find($topic);
        if ($model === null || !$model->topicable instanceof LiaScriptTopic) {
            return response()->json(['success' => false, 'message' => 'Not a LiaScript topic'], 404);
        }
        $course = $model->lesson?->course;
        if ($course === null || !Gate::forUser($request->user())->allows('attend', $course)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $origin = rtrim(trim((string) (config('ulams_uploads.content_origin') ?: config('scorm.content_origin'))), '/');
        if ($origin === '' || !$this->player->installed()) {
            return response()->json(['success' => false, 'message' => 'LiaScript courses cannot be played here yet (no content origin or the player is not installed).'], 503);
        }

        $document = $model->topicable->document;
        $version = $document?->version($document->current_version);
        if ($version === null) {
            return response()->json(['success' => false, 'message' => 'The course text is missing.'], 404);
        }

        $this->player->publishPlayer();
        $coursePath = $this->player->publishVersion($document, $version);
        $sections = LiaScriptPlayer::sections($version->markdown);
        $token = ProgressToken::issue((int) $request->user()->getKey(), $model->getKey(), (int) config('ulams_liascript.progress_token_ttl', 14400));

        if (!$model->progress()->where('user_id', $request->user()->getKey())->exists()) {
            $this->progress->updateInTopic($model, $request->user(), ProgressStatus::IN_PROGRESS);
        }

        $fragment = http_build_query([
            'api' => $request->getSchemeAndHttpHost(),
            'topic' => $model->getKey(),
            'token' => $token,
            'course' => $coursePath,
            'sections' => $sections,
        ], '', '&', PHP_QUERY_RFC3986);

        return response()->json(['success' => true, 'data' => [
            'url' => $origin . '/' . LiaScriptPlayer::PLAYER_DIR . '/index.html#' . $fragment,
            'sections' => $sections,
            'version' => $version->version,
        ], 'message' => 'OK']);
    }

    public function progress(Request $request, int $topic): JsonResponse
    {
        $userId = ProgressToken::verify($request->header(self::TOKEN_HEADER), $topic);
        if ($userId === null) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 401);
        }
        /** @var Topic|null $model */
        $model = Topic::query()->with('topicable')->find($topic);
        $user = Auth::getProvider()->retrieveById($userId);
        if ($model === null || $user === null || !$model->topicable instanceof LiaScriptTopic) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $document = $model->topicable->document;
        $version = $document?->version($document->current_version);
        $sections = $version ? LiaScriptPlayer::sections($version->markdown) : 1;
        $location = $request->input('location');
        $status = (string) $request->input('status', '');
        $done = in_array($status, ['completed', 'passed'], true)
            || (is_numeric($location) && (int) $location >= $sections - 1);

        $existing = $model->progress()->where('user_id', $userId)->first();
        if ($existing === null) {
            $this->progress->updateInTopic($model, $user, ProgressStatus::IN_PROGRESS);
        }
        if ($done && $existing?->status !== ProgressStatus::COMPLETE) {
            $this->progress->updateInTopic($model, $user, ProgressStatus::COMPLETE);
        }

        return response()->json(['success' => true, 'data' => ['status' => $done || $existing?->status === ProgressStatus::COMPLETE ? 'complete' : 'in_progress'], 'message' => 'OK']);
    }
}
