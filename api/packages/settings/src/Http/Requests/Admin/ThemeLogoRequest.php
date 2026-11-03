<?php

namespace Ulams\Settings\Http\Requests\Admin;

use finfo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Ulams\Settings\Models\Setting;
use Ulams\Settings\Support\SvgSanitizer;
use Ulams\Settings\Support\ThemeSettings;
use Ulams\Settings\Support\UnsafeSvg;

/**
 * The tenant logo: a PNG or an SVG of at most 200 KB (plan Q15). The type comes from the content, never from
 * the client's name or header. A PNG must decode as an image; an SVG goes through the allow-list sanitiser
 * and is refused, with the reason, when it carries anything but shapes. The sanitised text is what gets stored.
 */
class ThemeLogoRequest extends FormRequest
{
    public const MAX_BYTES = 200 * 1024;

    /** Pixels: a logo is shown at header height, a bigger file is a mistake. */
    public const MAX_PIXELS = 4096;

    private ?string $type = null;

    private ?string $svg = null;

    private ?array $size = null;

    public function authorize(): bool
    {
        return Gate::allows('update', Setting::class);
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'file'],
            'alt' => ['nullable', 'string', 'max:' . ThemeSettings::ALT_MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('logo');
            if (!$file instanceof UploadedFile || $validator->errors()->has('logo')) {
                return;
            }
            $path = $file->getRealPath();
            if ($path === false || !is_file($path)) {
                $validator->errors()->add('logo', __('The logo could not be read.'));

                return;
            }
            if (filesize($path) > self::MAX_BYTES) {
                $validator->errors()->add('logo', __('The logo may not be larger than :kb KB.', ['kb' => intdiv(self::MAX_BYTES, 1024)]));

                return;
            }
            $head = (string) file_get_contents($path, false, null, 0, 8);
            if ($head === "\x89PNG\r\n\x1a\n") {
                $this->checkPng($validator, $path);

                return;
            }
            // not a PNG: it can only be an SVG, and it has to prove it
            $sniffed = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
            if (!in_array($sniffed, ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'], true)) {
                $validator->errors()->add('logo', __('The logo must be a PNG or SVG file.'));

                return;
            }
            try {
                $this->svg = SvgSanitizer::sanitize((string) file_get_contents($path));
            } catch (UnsafeSvg $e) {
                $validator->errors()->add('logo', $e->getMessage());

                return;
            }
            $this->type = 'svg';
            $this->size = SvgSanitizer::size($this->svg);
        });
    }

    private function checkPng(Validator $validator, string $path): void
    {
        $info = @getimagesize($path);
        if ($info === false || $info[2] !== IMAGETYPE_PNG || $info[0] < 1 || $info[1] < 1) {
            $validator->errors()->add('logo', __('The file is not a valid PNG image.'));

            return;
        }
        if ($info[0] > self::MAX_PIXELS || $info[1] > self::MAX_PIXELS) {
            $validator->errors()->add('logo', __('The logo may not be larger than :px pixels in either direction.', ['px' => self::MAX_PIXELS]));

            return;
        }
        $this->type = 'png';
        $this->size = [$info[0], $info[1]];
    }

    /** `png` or `svg`, from the content. */
    public function extension(): string
    {
        return (string) $this->type;
    }

    public function mime(): string
    {
        return $this->type === 'svg' ? 'image/svg+xml' : 'image/png';
    }

    /** The bytes to store: the sanitised document for an SVG, the file itself for a PNG. */
    public function contents(): string
    {
        return $this->svg ?? (string) file_get_contents($this->file('logo')->getRealPath());
    }

    /** @return array{0:int|float,1:int|float}|null */
    public function dimensions(): ?array
    {
        return $this->size;
    }
}
