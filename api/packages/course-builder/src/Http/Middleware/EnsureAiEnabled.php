<?php

namespace Ulams\CourseBuilder\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ulams\Ai\Contracts\LlmClient;

/** Every builder endpoint answers 503 when AI is disabled; the rest of the LMS is unaffected. */
class EnsureAiEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (!app(LlmClient::class)->enabled()) {
            return response()->json([
                'success' => false,
                'message' => 'The AI Course Builder is disabled on this installation (set AI_DRIVER and an API key).',
                'code' => 'ai_disabled',
            ], 503);
        }

        return $next($request);
    }
}
