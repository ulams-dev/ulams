import { describe, expect, it } from '@jest/globals';
import {
  coverage,
  languageName,
  missingLanguages,
  translationDraft,
  universalTemplate,
} from './languages';

const EVENT = 'App\\Events\\PasswordForgotten';
const make = (id: number, locale: string | null, extra: Record<string, unknown> = {}) =>
  ({ id, name: `Template ${id}`, event: EVENT, default: true, locale, ...extra } as API.Template);

describe('template languages', () => {
  it('names a language in that language', () => {
    expect(languageName('pl')).toBe('Polski');
    expect(languageName('zh')).toBe('中文');
    expect(languageName('de')).toBe('DE');
    expect(languageName(null)).toBe('');
  });

  it('serves each language with its default template, else the first one written for it', () => {
    const draftPl = make(2, 'pl', { default: false });
    const defaultPl = make(3, 'pl');
    const served = coverage([make(1, 'en'), draftPl, defaultPl], EVENT);
    expect(served.en?.id).toBe(1);
    expect(served.pl?.id).toBe(3);
    expect(served.zh).toBeUndefined();
    expect(coverage([draftPl], EVENT).pl?.id).toBe(2);
  });

  it('lists the missing translations of an event only from its own templates', () => {
    const other = make(9, 'zh', { event: 'App\\Events\\Other' });
    expect(missingLanguages([make(1, 'en'), other], EVENT)).toEqual(['pl', 'zh']);
    expect(missingLanguages([make(1, 'en'), make(2, 'pl'), make(3, 'zh')], EVENT)).toEqual([]);
    expect(missingLanguages([], EVENT)).toEqual(['en', 'pl', 'zh']);
  });

  it('finds the template without a language', () => {
    expect(universalTemplate([make(1, 'en'), make(2, null)], EVENT)?.id).toBe(2);
    expect(universalTemplate([make(1, 'en')], EVENT)).toBeUndefined();
  });

  it('starts a translation as a copy of the sections, without the generated ones', () => {
    const draft = translationDraft(
      {
        name: 'Password reset (en)',
        event: EVENT,
        sections: [
          { key: 'title', content: 'Reset' },
          { key: 'content', content: '<mjml></mjml>' },
          { key: 'contentHtml', content: '<html></html>' },
        ],
      },
      'pl',
    );
    expect(draft).toEqual({
      title: 'Reset',
      content: '<mjml></mjml>',
      name: 'Password reset (pl)',
      event: EVENT,
      locale: 'pl',
      default: true,
    });
  });
});
