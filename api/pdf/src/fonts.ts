import { readFileSync } from 'node:fs';
import path from 'node:path';
import type { Font } from '@pdfme/common';

/**
 * Fonts bundled with the service (SIL Open Font License 1.1, see fonts/README.md).
 * Templates refer to them by key in `fontName`. All of them cover Polish
 * diacritics. The admin designer loads the same files through the API
 * (`GET /api/pdfs/fonts/{file}`), so a template looks the same in the designer
 * and in the rendered PDF.
 */
export const FONT_FILES: Record<string, string> = {
    'NotoSans-Regular': 'NotoSans-Regular.ttf',
    'NotoSans-Bold': 'NotoSans-Bold.ttf',
    'PlusJakartaSans-Regular': 'PlusJakartaSans-Regular.ttf',
    'PlusJakartaSans-Bold': 'PlusJakartaSans-Bold.ttf',
    'PlayfairDisplay-Bold': 'PlayfairDisplay-Bold.ttf',
    'SpaceGrotesk-Bold': 'SpaceGrotesk-Bold.ttf',
    'JetBrainsMono-Regular': 'JetBrainsMono-Regular.ttf',
    'Baloo2-Bold': 'Baloo2-Bold.ttf',
    'Nunito-Regular': 'Nunito-Regular.ttf'
};

/** Used for fields without `fontName` and for unknown font names. */
export const FALLBACK_FONT = 'NotoSans-Regular';

export interface FontManifestEntry {
    name: string;
    file: string;
    fallback: boolean;
}

export const fontManifest = (): FontManifestEntry[] =>
    Object.entries(FONT_FILES).map(([name, file]) => ({
        name,
        file,
        fallback: name === FALLBACK_FONT
    }));

export interface FontStore {
    /** pdfme `font` option (font bytes, fallback flag). */
    font: Font;
    /** Raw TTF bytes by file name, for GET /fonts/:file. */
    files: Map<string, Buffer>;
    has(name: string): boolean;
}

export const loadFonts = (dir: string): FontStore => {
    const font: Font = {};
    const files = new Map<string, Buffer>();
    for (const [name, file] of Object.entries(FONT_FILES)) {
        const bytes = readFileSync(path.join(dir, file));
        files.set(file, bytes);
        font[name] = {
            data: new Uint8Array(bytes.buffer, bytes.byteOffset, bytes.byteLength).slice().buffer,
            fallback: name === FALLBACK_FONT,
            subset: true
        };
    }
    return {
        font,
        files,
        has: (name: string) => Object.prototype.hasOwnProperty.call(font, name)
    };
};
