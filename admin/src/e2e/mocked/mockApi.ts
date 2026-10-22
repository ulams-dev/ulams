import type { Page, Route } from '@playwright/test';

/**
 * Deterministic in-browser API for the admin: every request to `API_ORIGIN` is fulfilled here, nothing
 * touches the network. State (the saved template) lives in the returned `MockApi`, so a test can save
 * and reopen. Unknown GETs answer an empty successful list, unknown writes fail loudly (HTTP 501) so a
 * new call never passes unnoticed.
 */
export const API_ORIGIN = 'http://api.mock';

export const EVENT = 'Ulams\\TemplatesPdf\\Events\\CertificateIssued';
const PDF_CHANNEL = 'Ulams\\TemplatesPdf\\Core\\PdfChannel';

export const VARIABLES = ['@VarUserName', '@VarCourseTitle', '@VarFinishDate'];
export const REQUIRED_VARIABLES = ['@VarUserName'];

export const EMAIL_EVENT = 'Ulams\\Auth\\Events\\PasswordForgotten';
const EMAIL_CHANNEL = 'Ulams\\TemplatesEmail\\Core\\EmailChannel';

export const EMAIL_VARIABLES = ['@VarUserName', '@VarActionLink', '@VarActionLinkExpiration'];
export const EMAIL_REQUIRED_VARIABLES = ['@VarActionLink'];

/** Representable MJML: opens in Visual mode without the conversion dialog. */
export const EMAIL_TEMPLATE = `<mjml lang="en">
  <mj-body>
    <mj-section>
      <mj-column>
        <mj-text>Hello @VarUserName, you asked to reset your password.</mj-text>
        <mj-button href="@VarActionLink">Reset password</mj-button>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>`;

/** 1x1 PNG, what the image upload endpoint "stores". */
export const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64',
);

/** pdfme template with the one required field. */
export const SAMPLE_TEMPLATE = {
  basePdf: { width: 297, height: 210, padding: [0, 0, 0, 0] },
  schemas: [
    [
      {
        name: '@VarUserName',
        type: 'text',
        content: 'Ada Lovelace',
        position: { x: 40, y: 80 },
        width: 200,
        height: 14,
        fontSize: 28,
      },
    ],
  ],
};

/** ReportBro JSON, what templates made before pdfme look like. */
export const LEGACY_TEMPLATE = {
  docElements: [{ elementType: 'text', id: 1, content: '${VarUserName}' }],
  documentProperties: { pageFormat: 'A4', orientation: 'landscape' },
  styles: [],
  parameters: [],
};

// Unsigned JWT that expires in 2100: the app decodes it after a token refresh to schedule the next one.
const b64 = (value: object) => Buffer.from(JSON.stringify(value)).toString('base64url');
const TOKEN = `${b64({ alg: 'none', typ: 'JWT' })}.${b64({ sub: '1', exp: 4102444800 })}.mock`;

const PERMISSIONS = ['access dashboard', 'template_list', 'template_read', 'template_update'];
const SETTINGS_PERMISSIONS = [
  'settings_list',
  'settings_read',
  'settings_create',
  'settings_update',
  'settings_delete',
];

/** A stored setting row, as GET /api/admin/settings returns it. */
export type MockSetting = {
  id: number;
  group: string;
  key: string;
  value: string;
  type: string;
  data: unknown;
};

/** What the Theme screen reads and writes: the `theme` settings group, the logo endpoint and the company name. */
export type MockTheme = {
  theme?: string;
  accent?: string;
  logoAlt?: string;
  /** Public URL of the stored logo. */
  logo?: string;
  company?: string;
  /** When set, POST /api/admin/theme/logo is refused with 422 and this reason. */
  logoRefusal?: string;
};

// A minimal valid one-page PDF, returned by the preview endpoint.
const PDF = Buffer.from(
  '%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n' +
    '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 100]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF',
);

const ok = (data: unknown, extra: Record<string, unknown> = {}) => ({
  success: true,
  data,
  message: 'OK',
  ...extra,
});

export type MockApi = {
  /** Content of the saved template (JSON string), as the API would return it. */
  content: () => string;
  /** Bodies of every save (PATCH /api/admin/templates/:id), in order. */
  saves: Record<string, any>[];
  /** Bodies of every POST /api/admin/pdfs/preview, in order. */
  previews: Record<string, any>[];
  /** Bodies of every POST /api/admin/templates/email/render, in order. */
  renders: Record<string, any>[];
  /** Number of images uploaded through POST /api/admin/templates/email/images. */
  uploads: () => number;
  /** Bodies of every POST /api/admin/templates/email/test-send, in order. */
  testSends: Record<string, any>[];
  /** Bodies of every POST /api/admin/templates (a template created, for example a translation), in order. */
  creates: Record<string, any>[];
  /** Writes to the theme settings (POST, PUT, DELETE /api/admin/settings), in order. */
  themeWrites: { method: string; key: string; value?: string }[];
  /** Logo uploads (POST /api/admin/theme/logo): file name, type, size and alt text, in order. */
  logoUploads: { name: string; type: string; size: number; alt: string | null }[];
  /** Number of DELETE /api/admin/theme/logo calls. */
  logoRemovals: () => number;
  /** The theme settings as stored now. */
  theme: () => Record<string, string>;
};

/** A template of the same event in another language, as the list endpoint returns it. */
export type MockSibling = { id: number; locale: string | null; name: string; content?: string };

export async function mockApi(
  page: Page,
  options: {
    template?: unknown;
    templateId?: number;
    kind?: 'pdf' | 'email';
    /** Language of the admin interface, for example `pl-PL`. */
    locale?: string;
    /** Language the e-mail template itself is written for (`en`, `pl`, `zh`); none by default. */
    templateLocale?: string | null;
    /** Other templates of the event (e-mail only), one per language. */
    siblings?: MockSibling[];
    /** Serve the Theme settings screen (adds the settings permissions) with this stored theme. */
    theme?: MockTheme;
  } = {},
): Promise<MockApi> {
  const email = options.kind === 'email';
  const id = options.templateId ?? 7;
  let content = email
    ? String(options.template ?? EMAIL_TEMPLATE)
    : JSON.stringify(options.template ?? SAMPLE_TEMPLATE);
  let uploads = 0;
  const state: MockApi = {
    content: () => content,
    saves: [],
    previews: [],
    renders: [],
    uploads: () => uploads,
    testSends: [],
    creates: [],
    themeWrites: [],
    logoUploads: [],
    logoRemovals: () => logoRemovals,
    theme: () => Object.fromEntries(themeRows.map((r) => [r.key, r.value])),
  };
  let logoRemovals = 0;
  let nextSettingId = 500;
  const themeRows: MockSetting[] = [];
  const addThemeRow = (key: string, value: string | undefined, type = 'text') => {
    if (value !== undefined) {
      themeRows.push({ id: nextSettingId++, group: 'theme', key, value, type, data: value });
    }
  };
  addThemeRow('theme', options.theme?.theme);
  addThemeRow('accent', options.theme?.accent);
  addThemeRow('logoAlt', options.theme?.logoAlt);
  addThemeRow('logo', options.theme?.logo ? 'theme/logo-1.png' : undefined, 'image');
  if (options.theme?.logo) {
    themeRows[themeRows.length - 1]!.data = options.theme.logo;
  }
  const company = options.theme?.company ?? 'Acme Coffee';
  const siblings = options.siblings ?? [];
  const created = new Map<number, Record<string, any>>();

  // The API origin comes from /runtime-config.json (scripts/serve-dist.cjs), like in the Docker image.
  await page.addInitScript(
    ({ token, locale }) => {
      window.localStorage.setItem('TOKEN', token);
      window.localStorage.setItem('umi_locale', locale);
    },
    { token: TOKEN, locale: options.locale ?? 'en-US' },
  );

  const template = () =>
    email
      ? {
          id,
          name: 'Password reset',
          type: 'email',
          event: EMAIL_EVENT,
          channel: EMAIL_CHANNEL,
          default: true,
          locale: options.templateLocale ?? null,
          sections: [
            { key: 'title', content: 'Reset your password' },
            { key: 'content', content },
          ],
        }
      : {
          id,
          name: 'Course certificate',
          type: 'pdf',
          event: EVENT,
          channel: PDF_CHANNEL,
          default: true,
          sections: [{ key: 'content', content }],
        };

  const sibling = (item: MockSibling) => ({
    id: item.id,
    name: item.name,
    type: 'email',
    event: EMAIL_EVENT,
    channel: EMAIL_CHANNEL,
    default: true,
    locale: item.locale,
    sections: [
      { key: 'title', content: `Reset your password (${item.locale ?? 'none'})` },
      { key: 'content', content: item.content ?? EMAIL_TEMPLATE },
    ],
  });

  const handler = async (route: Route) => {
    const request = route.request();
    const url = new URL(request.url());
    const path = url.pathname;
    const method = request.method();
    const cors = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Headers': '*',
      'Access-Control-Allow-Methods': '*',
    };
    const json = (body: unknown, status = 200) =>
      route.fulfill({
        status,
        contentType: 'application/json',
        headers: cors,
        body: JSON.stringify(body),
      });

    if (method === 'OPTIONS') {
      return route.fulfill({ status: 204, headers: cors });
    }

    if (method === 'GET') {
      if (path.startsWith('/storage/email/')) {
        return route.fulfill({ status: 200, contentType: 'image/png', headers: cors, body: PNG });
      }
      if (path === '/api/profile/me') {
        return json(
          ok({
            id: 1,
            email: 'admin@example.test',
            first_name: 'Ada',
            last_name: 'Admin',
            roles: ['admin'],
            permissions: options.theme ? [...PERMISSIONS, ...SETTINGS_PERMISSIONS] : PERMISSIONS,
          }),
        );
      }
      if (path === '/api/auth/refresh') {
        return json(ok({ token: TOKEN, expires_at: '2100-01-01T00:00:00Z' }));
      }
      if (path === '/api/config') {
        return json(ok({}));
      }
      if (path === '/api/settings') {
        return json(ok(options.theme ? { global: { companyName: company } } : {}));
      }
      if (path === '/api/admin/settings' && options.theme) {
        const rows = url.searchParams.get('group') === 'theme' ? themeRows : [];
        return json(ok(rows));
      }
      if (path.startsWith('/storage/theme/')) {
        return route.fulfill({ status: 200, contentType: 'image/png', headers: cors, body: PNG });
      }
      if (path === '/api/core/packages') {
        return json(ok({ 'templates-pdf': '1.0.0', 'templates-email': '1.0.0' }));
      }
      if (path === '/api/admin/templates/variables' && email) {
        return json(
          ok({
            [EMAIL_EVENT]: {
              [EMAIL_CHANNEL]: {
                assignable_class: '',
                class: EMAIL_EVENT,
                required_variables: EMAIL_REQUIRED_VARIABLES,
                variables: EMAIL_VARIABLES,
                sections: {
                  title: {
                    type: 'text',
                    required_variables: [],
                    required: true,
                    default_content: 'Reset your password',
                    readonly: false,
                  },
                  content: {
                    type: 'mjml',
                    required_variables: EMAIL_REQUIRED_VARIABLES,
                    required: true,
                    default_content: EMAIL_TEMPLATE,
                    readonly: false,
                  },
                },
              },
            },
          }),
        );
      }
      if (path === '/api/admin/templates/variables') {
        return json(
          ok({
            [EVENT]: {
              [PDF_CHANNEL]: {
                assignable_class: '',
                class: EVENT,
                required_variables: REQUIRED_VARIABLES,
                variables: VARIABLES,
                sections: {
                  content: {
                    type: 'fabric.js',
                    required_variables: REQUIRED_VARIABLES,
                    required: true,
                    default_content: JSON.stringify(SAMPLE_TEMPLATE),
                    readonly: false,
                  },
                },
              },
            },
          }),
        );
      }
      if (path === `/api/admin/templates/${id}`) {
        return json(ok(template()));
      }
      if (path === '/api/admin/templates' && email) {
        const rows = [template(), ...siblings.map(sibling), ...created.values()];
        return json(
          ok(rows, { meta: { total: rows.length, current_page: 1, per_page: 100, last_page: 1 } }),
        );
      }
      const other = /^\/api\/admin\/templates\/(\d+)$/.exec(path);
      if (other && email) {
        const found =
          siblings.find((item) => item.id === Number(other[1])) &&
          sibling(siblings.find((item) => item.id === Number(other[1])) as MockSibling);
        const made = created.get(Number(other[1]));
        if (found || made) {
          return json(ok(found ?? made));
        }
      }
      if (path === '/api/pdfs/fonts') {
        // no font files: the designer falls back to its bundled default font
        return json(ok([]));
      }
      if (path === '/api/admin/pdfs') {
        return json(ok([], { meta: { total: 0, current_page: 1, per_page: 10, last_page: 1 } }));
      }
      return json(ok([], { meta: { total: 0, current_page: 1, per_page: 10, last_page: 1 } }));
    }

    if (method === 'PATCH' && path === `/api/admin/templates/${id}`) {
      const body = request.postDataJSON();
      state.saves.push(body);
      const section = (body.sections ?? []).find((s: { key: string }) => s.key === 'content');
      if (section) {
        content = section.content;
      }
      return json(ok(template(), { message: 'Template updated' }));
    }

    if (method === 'POST' && path === '/api/admin/templates/email/render') {
      const body = request.postDataJSON();
      state.renders.push(body);
      return json(
        ok({
          title: body.title,
          html: `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${
            body.title
          }</title></head><body><p>Rendered preview, ${
            String(body.content).length
          } characters of MJML.</p></body></html>`,
          errors: [],
          missing_variables: [],
          warnings: [],
        }),
      );
    }

    if (method === 'POST' && path === '/api/admin/templates/email/test-send') {
      const body = request.postDataJSON();
      state.testSends.push(body);
      return json(
        ok({
          title: `[Test] ${body.title}`,
          html: '<p>sent</p>',
          errors: [],
          missing_variables: [],
          warnings: [],
          sent: true,
          recipient: 'admin@example.test',
          locale: body.locale ?? null,
        }),
      );
    }

    if (method === 'POST' && path === '/api/admin/templates' && email) {
      const body = request.postDataJSON();
      state.creates.push(body);
      const next = { ...body, id: 100 + state.creates.length, type: 'email' };
      created.set(next.id, next);
      return json(ok(next, { message: 'Template created' }), 201);
    }

    if (method === 'POST' && path === '/api/admin/templates/email/images') {
      uploads += 1;
      return json(
        ok({
          url: `${API_ORIGIN}/storage/email/image-${uploads}.png`,
          name: `image-${uploads}.png`,
          mime: 'image/png',
          size: PNG.length,
          width: 1,
          height: 1,
        }),
      );
    }

    if (options.theme && path.startsWith('/api/admin/settings')) {
      const id = Number(path.split('/')[4]);
      if (method === 'POST' && path === '/api/admin/settings') {
        const body = request.postDataJSON();
        state.themeWrites.push({ method, key: body.key, value: body.value });
        themeRows.push({
          id: nextSettingId++,
          group: body.group,
          key: body.key,
          value: body.value,
          type: body.type,
          data: body.value,
        });
        return json(ok(themeRows[themeRows.length - 1]));
      }
      const row = themeRows.find((r) => r.id === id);
      if (method === 'PUT' && row) {
        const body = request.postDataJSON();
        state.themeWrites.push({ method, key: row.key, value: body.value });
        row.value = body.value;
        row.data = body.value;
        return json(ok(row));
      }
      if (method === 'DELETE' && row) {
        state.themeWrites.push({ method, key: row.key });
        themeRows.splice(themeRows.indexOf(row), 1);
        return json(ok({}));
      }
    }

    if (options.theme && path === '/api/admin/theme/logo') {
      if (method === 'DELETE') {
        logoRemovals += 1;
        for (const key of ['logo', 'logoAlt']) {
          const at = themeRows.findIndex((r) => r.key === key);
          if (at >= 0) themeRows.splice(at, 1);
        }
        return json(ok({}));
      }
      if (method === 'POST') {
        if (options.theme.logoRefusal) {
          return json(
            {
              success: false,
              message: 'The given data was invalid.',
              errors: { logo: [options.theme.logoRefusal] },
            },
            422,
          );
        }
        // multipart body: the file name, type and alt text are read from the raw text
        const raw = request.postDataBuffer()?.toString('latin1') ?? '';
        const name = /name="logo"; filename="([^"]*)"/.exec(raw)?.[1] ?? '';
        const type = /name="logo"[^]*?Content-Type: ([^\r\n]*)/.exec(raw)?.[1] ?? '';
        const alt = /name="alt"\r\n\r\n([^\r]*)/.exec(raw)?.[1] ?? null;
        state.logoUploads.push({ name, type, size: request.postDataBuffer()?.length ?? 0, alt });
        for (const key of ['logo', 'logoAlt']) {
          const at = themeRows.findIndex((r) => r.key === key);
          if (at >= 0) themeRows.splice(at, 1);
        }
        const url = `${API_ORIGIN}/storage/theme/logo-${state.logoUploads.length}.png`;
        themeRows.push({
          id: nextSettingId++,
          group: 'theme',
          key: 'logo',
          value: 'theme/logo-new.png',
          type: 'image',
          data: url,
        });
        if (alt) addThemeRow('logoAlt', alt);
        return json(ok({ url, mime: type, alt }));
      }
    }

    if (method === 'POST' && path === '/api/admin/pdfs/preview') {
      state.previews.push(request.postDataJSON());
      return route.fulfill({
        status: 200,
        contentType: 'application/pdf',
        headers: cors,
        body: PDF,
      });
    }

    return json({ success: false, message: `mock: ${method} ${path} is not handled` }, 501);
  };

  await page.route(`${API_ORIGIN}/**`, handler);
  return state;
}
