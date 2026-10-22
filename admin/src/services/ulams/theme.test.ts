import { request } from 'umi';
import {
  LOGO_MAX_BYTES,
  logoProblem,
  refusal,
  removeLogo,
  themeSettings,
  themeValues,
  uploadLogo,
} from './theme';

jest.mock('umi', () => ({ request: jest.fn().mockResolvedValue({ success: true, data: {} }) }));

describe('theme service', () => {
  beforeEach(() => (request as jest.Mock).mockClear());

  it('lists the theme group without paging', async () => {
    await themeSettings();
    const [url, options] = (request as jest.Mock).mock.calls[0];
    expect(url).toBe('/api/admin/settings');
    expect(options.params).toEqual({ group: 'theme', per_page: -1 });
  });

  it('uploads the logo as multipart data with the trimmed alt text', async () => {
    const file = new File(['<svg/>'], 'logo.svg', { type: 'image/svg+xml' });
    await uploadLogo(file, '  Acme Coffee ');
    const [url, options] = (request as jest.Mock).mock.calls[0];
    expect(url).toBe('/api/admin/theme/logo');
    expect(options.method).toBe('POST');
    expect((options.data as FormData).get('logo')).toBe(file);
    expect((options.data as FormData).get('alt')).toBe('Acme Coffee');
  });

  it('leaves an empty alt text out and removes with DELETE', async () => {
    await uploadLogo(new File(['x'], 'a.png'), '   ');
    expect([...((request as jest.Mock).mock.calls[0][1].data as FormData).keys()]).toEqual([
      'logo',
    ]);
    await removeLogo();
    expect((request as jest.Mock).mock.calls[1][1].method).toBe('DELETE');
  });

  it('knows the two formats and the 200 KB limit before sending', () => {
    expect(logoProblem({ name: 'a.png', type: 'image/png', size: 10 })).toBeNull();
    expect(logoProblem({ name: 'a.SVG', type: '', size: 10 })).toBeNull();
    expect(logoProblem({ name: 'a.gif', type: 'image/gif', size: 10 })).toBe('type');
    expect(logoProblem({ name: 'a.php', type: '', size: 10 })).toBe('type');
    expect(logoProblem({ name: 'a.png', type: 'image/png', size: LOGO_MAX_BYTES })).toBeNull();
    expect(logoProblem({ name: 'a.png', type: 'image/png', size: LOGO_MAX_BYTES + 1 })).toBe(
      'size',
    );
  });

  it('keeps only the theme rows, by key', () => {
    const row = (group: string, key: string) => ({ id: 1, group, key, value: 'x' } as API.Setting);
    const values = themeValues([
      row('theme', 'theme'),
      row('theme', 'accent'),
      row('global', 'theme'),
      row('theme', 'other'),
    ]);
    expect(Object.keys(values).sort()).toEqual(['accent', 'theme']);
  });

  it('reads the reason of a refusal', () => {
    expect(
      refusal({
        response: {
          data: { errors: { logo: ['The element <script> is not allowed in a logo.'] } },
        },
      }),
    ).toBe('The element <script> is not allowed in a logo.');
    expect(refusal({ response: { data: { message: 'Forbidden' } } })).toBe('Forbidden');
    expect(refusal(new Error('x'))).toBeNull();
  });
});
