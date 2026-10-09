import { request } from 'umi';
import type { InteractiveStep } from './interactive';
import {
  addInteractiveVersion,
  createInteractivePackage,
  formatBytes,
  interactivePackages,
  isInteractiveEnabled,
  localised,
  needsPassScore,
  previewInteractive,
  rangeProblem,
  stepOptions,
  topicFields,
} from './interactive';

jest.mock('umi', () => ({ request: jest.fn().mockResolvedValue({ success: true, data: {} }) }));

const manifest: { defaultLocale: string; steps: InteractiveStep[] } = {
  defaultLocale: 'en',
  steps: [
    { id: 'intro', title: { en: 'Intro', pl: 'Wstęp' }, text: { en: 'a', pl: 'b' } },
    { id: 'middle', title: { en: 'Middle' }, text: { en: 'c' } },
    { id: 'last', title: { pl: 'Koniec' }, text: { pl: 'd' } },
  ],
};

describe('interactive service', () => {
  beforeEach(() => (request as jest.Mock).mockClear());

  it('uploads a package as multipart data with the optional title and note', async () => {
    const file = new File(['zip'], 'gravity.zip');
    await createInteractivePackage(file, 'Gravity', 'first');
    const [url, options] = (request as jest.Mock).mock.calls[0];
    expect(url).toBe('/api/admin/interactive');
    expect(options.method).toBe('POST');
    expect((options.data as FormData).get('file')).toBe(file);
    expect((options.data as FormData).get('title')).toBe('Gravity');
    expect((options.data as FormData).get('change_note')).toBe('first');
  });

  it('leaves empty fields out of the upload', async () => {
    await addInteractiveVersion(3, new File(['zip'], 'v2.zip'));
    const [url, options] = (request as jest.Mock).mock.calls[0];
    expect(url).toBe('/api/admin/interactive/3/versions');
    expect([...(options.data as FormData).keys()]).toEqual(['file']);
  });

  it('lists with a search and previews a version', async () => {
    await interactivePackages({ current: 2, pageSize: 10, search: 'grav' });
    expect(request).toHaveBeenCalledWith('/api/admin/interactive', {
      method: 'GET',
      params: { page: 2, per_page: 10, search: 'grav' },
    });
    await previewInteractive(4, 2);
    expect(request).toHaveBeenCalledWith('/api/admin/interactive/4/preview', {
      method: 'GET',
      params: { version: 2 },
    });
  });
});

describe('step helpers', () => {
  it('builds the step select options from a manifest, in order, in the wanted locale', () => {
    expect(stepOptions(manifest, 'pl')).toEqual([
      { value: 'intro', label: '1. Wstęp (intro)' },
      { value: 'middle', label: '2. Middle (middle)' },
      { value: 'last', label: '3. Koniec (last)' },
    ]);
    expect(stepOptions(null)).toEqual([]);
  });

  it('falls back to the default locale, then to any', () => {
    expect(localised({ en: 'E', pl: 'P' }, 'de', 'en')).toBe('E');
    expect(localised({ pl: 'P' }, 'de', 'en')).toBe('P');
    expect(localised(undefined, 'de', 'en')).toBe('');
  });

  it('finds a range problem', () => {
    expect(rangeProblem(manifest, 'middle', 'last')).toBeNull();
    expect(rangeProblem(manifest, undefined, undefined)).toBeNull();
    expect(rangeProblem(manifest, 'x', 'last')).toBe('unknown-start');
    expect(rangeProblem(manifest, 'intro', 'x')).toBe('unknown-end');
    expect(rangeProblem(manifest, 'last', 'intro')).toBe('order');
  });
});

describe('topic fields', () => {
  it('pins a version, or follows the latest one, with booleans as 1 and 0', () => {
    expect(topicFields({ value: 5, version: 3 })).toMatchObject({
      value: 5,
      follow_latest: 0,
      version: 3,
    });
    expect(topicFields({ value: 5, version: 3, follow_latest: true })).toMatchObject({
      follow_latest: 1,
      version: undefined,
    });
  });

  it('sends the pass score only for the on_score rule and clears empty steps', () => {
    expect(needsPassScore('on_score')).toBe(true);
    expect(needsPassScore('on_complete')).toBe(false);
    expect(topicFields({ value: 1, completion_rule: 'on_score', pass_score: 80 }).pass_score).toBe(
      80,
    );
    expect(
      topicFields({ value: 1, completion_rule: 'on_complete', pass_score: 80 }).pass_score,
    ).toBeUndefined();
    expect(topicFields({ value: 1 })).toMatchObject({
      start_step: '',
      end_step: '',
      completion_rule: 'on_range_end',
      display: 'inline',
      height: 640,
    });
  });

  it('formats sizes', () => {
    expect(formatBytes(2048)).toBe('2 KB');
    expect(formatBytes(5 * 1024 * 1024)).toBe('5.0 MB');
  });
});

describe('tenant switch', () => {
  it('is on unless the public config says false', () => {
    expect(isInteractiveEnabled(undefined)).toBe(true);
    expect(isInteractiveEnabled({})).toBe(true);
    expect(isInteractiveEnabled({ ulams_interactive: { enabled: true } })).toBe(true);
    expect(isInteractiveEnabled({ ulams_interactive: { enabled: false } })).toBe(false);
    expect(isInteractiveEnabled({ ulams_interactive: { enabled: '0' } })).toBe(false);
  });
});
