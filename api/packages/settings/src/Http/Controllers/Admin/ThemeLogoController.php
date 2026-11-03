<?php

namespace Ulams\Settings\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Settings\Http\Requests\Admin\ThemeLogoRequest;
use Ulams\Settings\Models\Setting;
use Ulams\Settings\Services\Contracts\SettingsServiceContract;
use Ulams\Settings\Support\ThemeSettings;

class ThemeLogoController extends UlamsBaseController
{
    public const DIRECTORY = 'theme';

    public function __construct(private readonly SettingsServiceContract $settings)
    {
    }

    /**
     * @OA\Post(
     *     tags={"Admin Settings"},
     *     path="/api/admin/theme/logo", operationId="admin.theme.logo.store",
     *     summary="Upload the tenant logo",
     *     description="Stores a PNG or SVG logo (at most 200 KB; a PNG at most 4096 px a side) in the storage of the current tenant and saves it as the public setting theme.logo, with an optional text alternative as theme.logoAlt. The type is taken from the content. An SVG is checked against an allow-list and stored in its re-serialised form; one with a script, style sheet, event attribute, DOCTYPE, foreignObject or a reference to another address is refused with the reason. The stored name is random and the previous logo file is removed. Needs settings_update. Throttled to 20 requests per minute per user.",
     *     security={{"passport": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"logo"},
     *                 @OA\Property(property="logo", type="string", format="binary", description="PNG or SVG file, at most 200 KB"),
     *                 @OA\Property(property="alt", type="string", maxLength=120, description="Text alternative of the logo; the company name when empty")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="the stored logo", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
     *     @OA\Response(response=401, description="not signed in"),
     *     @OA\Response(response=403, description="missing permission"),
     *     @OA\Response(response=422, description="not an accepted logo, or too large"),
     *     @OA\Response(response=429, description="too many requests"),
     * )
     */
    public function store(ThemeLogoRequest $request): JsonResponse
    {
        $disk = Storage::disk();
        $path = self::DIRECTORY . '/logo-' . Str::uuid() . '.' . $request->extension();
        if ($disk->put($path, $request->contents(), 'public') === false) {
            return $this->sendError(__('The logo could not be stored.'), 500);
        }
        $this->forget();
        $this->settings->put(ThemeSettings::GROUP, ThemeSettings::LOGO_KEY, $path, 'image');
        $alt = trim((string) $request->input('alt', ''));
        if ($alt !== '') {
            $this->settings->put(ThemeSettings::GROUP, 'logoAlt', $alt);
        } else {
            Setting::query()->where(['group' => ThemeSettings::GROUP, 'key' => 'logoAlt'])->delete();
        }
        $size = $request->dimensions();

        return $this->sendResponse([
            'url' => $disk->url($path),
            'name' => $path,
            'mime' => $request->mime(),
            'size' => strlen($request->contents()),
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'alt' => $alt !== '' ? $alt : null,
        ], __('Logo saved.'));
    }

    /**
     * @OA\Delete(
     *     tags={"Admin Settings"},
     *     path="/api/admin/theme/logo", operationId="admin.theme.logo.destroy",
     *     summary="Remove the tenant logo",
     *     description="Removes theme.logo and theme.logoAlt and deletes the stored file; the learner site falls back to the company name as text. Needs settings_update.",
     *     security={{"passport": {}}},
     *     @OA\Response(response=200, description="removed (also when there was no logo)", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")),
     *     @OA\Response(response=401, description="not signed in"),
     *     @OA\Response(response=403, description="missing permission"),
     * )
     */
    public function destroy(Request $request): JsonResponse
    {
        Gate::authorize('update', Setting::class);
        $this->forget();
        Setting::query()->where('group', ThemeSettings::GROUP)->whereIn('key', [ThemeSettings::LOGO_KEY, 'logoAlt'])->delete();

        return $this->sendSuccess(__('Logo removed.'));
    }

    /** Deletes the file of the current logo, when it is one this endpoint stored. */
    private function forget(): void
    {
        $current = Setting::query()->where(['group' => ThemeSettings::GROUP, 'key' => ThemeSettings::LOGO_KEY])->value('value');
        if (is_string($current) && preg_match('#^' . self::DIRECTORY . '/logo-[0-9a-f-]{36}\.(png|svg)$#', $current) === 1) {
            Storage::disk()->delete($current);
        }
    }
}
