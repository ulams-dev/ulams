<?php

namespace Ulams\TemplatesEmail\Http\Requests;

use finfo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Ulams\Templates\Models\Template;

/**
 * An image for an e-mail template. E-mail clients fetch images remotely and most of them do not
 * render SVG, so only raster formats are accepted: SVG is refused (it can carry scripts and is not
 * sanitised here). The type is taken from the content, never from the client's name or header.
 */
class EmailImageUploadRequest extends FormRequest
{
    /** Sniffed MIME type to the extension the file is stored with. */
    public const TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function authorize(): bool
    {
        return Gate::allows('update', Template::class);
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file'],
        ];
    }

    public static function maxBytes(): int
    {
        return (int) config('ulams_templates_email.images.max_kb', 2048) * 1024;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('image');
            if (!$file instanceof UploadedFile || $validator->errors()->has('image')) {
                return;
            }
            $path = $file->getRealPath();
            if ($path === false || !is_file($path)) {
                $validator->errors()->add('image', __('The image could not be read.'));

                return;
            }
            if (filesize($path) > self::maxBytes()) {
                $validator->errors()->add('image', __('The image may not be larger than :kb KB.', ['kb' => intdiv(self::maxBytes(), 1024)]));

                return;
            }
            $sniffed = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
            if (!isset(self::TYPES[$sniffed])) {
                $validator->errors()->add('image', __('The image must be a PNG, JPEG, GIF or WebP file. SVG is not supported in e-mails.'));

                return;
            }
            // A real, decodable raster image, not a script with an image header.
            $info = @getimagesize($path);
            if ($info === false || $info[0] < 1 || $info[1] < 1) {
                $validator->errors()->add('image', __('The file is not a valid image.'));
            }
        });
    }

    public function extension(): string
    {
        return self::TYPES[(string) (new finfo(FILEINFO_MIME_TYPE))->file($this->file('image')->getRealPath())];
    }
}
