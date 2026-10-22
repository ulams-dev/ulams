/**
 * @jest-environment jsdom
 */
import { expect, jest } from '@jest/globals';
import { resizedImage } from './files';
import { contentImageUrl, imageService, resolveImage } from './imageService';

// files.ts imports the umi runtime, which cannot load under jest
jest.mock('umi', () => ({ request: jest.fn() }));

const STORAGE = 'https://staging-storage.ulams.app';

const setWindow = (settings: Record<string, string | undefined>) => {
  for (const [key, value] of Object.entries(settings)) {
    (window as unknown as Record<string, unknown>)[key] = value;
  }
};

beforeEach(() => {
  setWindow({
    REACT_APP_API_URL: 'https://api.example.test',
    REACT_APP_IMAGE_SERVICE: undefined,
    REACT_APP_IMAGE_SOURCES: STORAGE,
    REACT_APP_IMAGE_TRANSFORM_ORIGIN: undefined,
  });
});

describe('php mode (the default)', () => {
  it('is the default when the setting is absent, empty or unknown', () => {
    expect(imageService()).toBe('php');
    setWindow({ REACT_APP_IMAGE_SERVICE: '' });
    expect(imageService()).toBe('php');
    setWindow({ REACT_APP_IMAGE_SERVICE: 'imgix' });
    expect(imageService()).toBe('php');
  });

  it('builds exactly the URLs the admin always built', () => {
    expect(resizedImage('wysiwyg/a b.png')).toBe(
      'https://api.example.test/api/images/img?path=wysiwyg/a b.png&w=1000',
    );
    expect(resizedImage('wysiwyg/a.png', 600, `${STORAGE}/wysiwyg/a.png`)).toBe(
      'https://api.example.test/api/images/img?path=wysiwyg/a.png&w=600',
    );
    expect(resolveImage({ path: 'c/1.png', url: `${STORAGE}/c/1.png` }, { size: 240 })).toEqual({
      src: 'https://api.example.test/api/images/img?path=c/1.png&w=240',
      srcSet: undefined,
      original: `${STORAGE}/c/1.png`,
    });
  });

  it('keeps the srcSizes list as given', () => {
    const { src, srcSet } = resolveImage({ path: 'c/1.png' }, { srcSizes: [100, 300] });
    expect(src).toBe('https://api.example.test/api/images/img?path=c/1.png&w=100');
    expect(srcSet).toBe(
      'https://api.example.test/api/images/img?path=c/1.png&w=100 100w,https://api.example.test/api/images/img?path=c/1.png&w=300 300w',
    );
  });

  it('stores the resizer URL in markdown', () => {
    expect(contentImageUrl({ path: 'wysiwyg/a.png', url: `${STORAGE}/wysiwyg/a.png` })).toBe(
      'https://api.example.test/api/images/img?path=wysiwyg/a.png&w=1000',
    );
  });
});

describe('cloudflare mode', () => {
  beforeEach(() => setWindow({ REACT_APP_IMAGE_SERVICE: 'cloudflare' }));
  const image = { path: 'courses/1.png', url: `${STORAGE}/courses/1.png` };

  it('uses /cdn-cgi/image on the page host with distinct preset widths', () => {
    const { src, srcSet, original } = resolveImage(image, { size: 240, preset: 'card' });
    expect(src).toBe(
      `/cdn-cgi/image/width=240,quality=78,format=auto,fit=scale-down,onerror=redirect/${STORAGE}/courses/1.png`,
    );
    expect(src).not.toContain('/api/images/img');
    expect(original).toBe(image.url);
    const widths = (srcSet ?? '').split(', ').map((entry) => entry.split(' ').pop());
    expect(widths).toEqual(['240w', '480w']);
    expect(new Set((srcSet ?? '').split(', ').map((entry) => entry.split(' ')[0])).size).toBe(2);
  });

  it('uses the avatar preset', () => {
    const { src, srcSet } = resolveImage(image, { size: 600, preset: 'avatar' });
    expect(src).toContain('width=600,quality=80');
    expect(srcSet?.split(', ')).toHaveLength(3);
  });

  it('honours a transform origin', () => {
    setWindow({ REACT_APP_IMAGE_TRANSFORM_ORIGIN: 'https://ulams.app' });
    expect(
      resolveImage(image, { size: 240, preset: 'card' }).src.startsWith(
        'https://ulams.app/cdn-cgi/image/',
      ),
    ).toBe(true);
  });

  it('leaves files from a foreign origin, SVG and GIF untouched', () => {
    expect(
      resolveImage({ url: 'https://elsewhere.test/a.png' }, { size: 240, preset: 'card' }).src,
    ).toBe('https://elsewhere.test/a.png');
    expect(resolveImage({ url: `${STORAGE}/a.svg` }, { size: 240, preset: 'card' }).src).toBe(
      `${STORAGE}/a.svg`,
    );
    expect(
      resolveImage({ url: `${STORAGE}/a.gif` }, { size: 240, preset: 'card' }).srcSet,
    ).toBeUndefined();
  });

  it('falls back to the PHP resizer for a file with no public URL', () => {
    expect(resolveImage({ path: 'c/2.png' }, { size: 240, preset: 'card' }).src).toBe(
      'https://api.example.test/api/images/img?path=c/2.png&w=240',
    );
  });

  it('stores the plain public URL in markdown and never an /api/images/img URL', () => {
    expect(resizedImage('wysiwyg/a.png', 1000, `${STORAGE}/wysiwyg/a.png`)).toBe(
      `${STORAGE}/wysiwyg/a.png`,
    );
    expect(contentImageUrl({ path: 'wysiwyg/a.png' })).toContain('/api/images/img');
  });
});

describe('noop mode', () => {
  beforeEach(() => setWindow({ REACT_APP_IMAGE_SERVICE: 'noop' }));

  it('serves originals without srcset', () => {
    expect(
      resolveImage({ path: 'c/1.png', url: `${STORAGE}/c/1.png` }, { size: 240, preset: 'card' }),
    ).toEqual({
      src: `${STORAGE}/c/1.png`,
      srcSet: undefined,
      original: `${STORAGE}/c/1.png`,
    });
    expect(resizedImage('c/1.png', 1000, `${STORAGE}/c/1.png`)).toBe(`${STORAGE}/c/1.png`);
  });

  it('treats the front-only astro mode as noop', () => {
    setWindow({ REACT_APP_IMAGE_SERVICE: 'astro' });
    const warn = jest.spyOn(console, 'warn').mockImplementation(() => undefined);
    expect(imageService()).toBe('noop');
    warn.mockRestore();
  });
});
