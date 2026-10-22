/** Languages the admin offers a template for. The API accepts any 2-3 letter code. */
export const TEMPLATE_LANGUAGES = ['en', 'pl', 'zh'] as const;

export type TemplateLanguage = (typeof TEMPLATE_LANGUAGES)[number];

/** Value of the "no language" option: the template used when a language has none. */
export const NO_LANGUAGE = '';

const NAMES: Record<string, string> = { en: 'English', pl: 'Polski', zh: '中文' };

/** Name of a language in that language, so it is recognisable whatever the admin language is. */
export const languageName = (code?: string | null): string =>
  code ? NAMES[code] ?? code.toUpperCase() : '';

type Sibling = Pick<API.Template, 'id' | 'event' | 'channel' | 'default' | 'locale' | 'name'>;

/**
 * The template that serves each language of one event: the default template of that language, else the
 * first one written for it. Languages without any template are missing translations.
 */
export const coverage = <T extends Sibling>(
  templates: T[],
  event?: string,
  languages: readonly string[] = TEMPLATE_LANGUAGES,
): Record<string, T | undefined> => {
  const own = templates.filter((template) => template.event === event);
  const result: Record<string, T | undefined> = {};
  languages.forEach((language) => {
    const written = own.filter((template) => template.locale === language);
    result[language] = written.find((template) => template.default) ?? written[0];
  });
  return result;
};

export const missingLanguages = (
  templates: Sibling[],
  event?: string,
  languages: readonly string[] = TEMPLATE_LANGUAGES,
): string[] => {
  const served = coverage(templates, event, languages);
  return languages.filter((language) => !served[language]);
};

/** The template without a language of an event, if there is one (what every template was before). */
export const universalTemplate = <T extends Sibling>(
  templates: T[],
  event?: string,
): T | undefined => {
  const own = templates.filter((template) => template.event === event && !template.locale);
  return own.find((template) => template.default) ?? own[0];
};

/** Sections that are generated on save and must not be copied into a translation. */
const GENERATED_SECTIONS = ['contentHtml'];

/**
 * Form values for a new template in `locale` that starts as a copy of `source`: its sections (except the
 * generated ones), a name that says the language, and the default flag so it is the one sent for that language.
 */
export const translationDraft = (
  source: Pick<API.Template, 'name' | 'event' | 'sections'>,
  locale: string,
): Record<string, string | boolean | undefined> => {
  const baseName = source.name.replace(/\s*\([a-z]{2,3}\)\s*$/i, '');
  const sections = Object.fromEntries(
    (source.sections ?? [])
      .filter((section) => !GENERATED_SECTIONS.includes(section.key))
      .map((section) => [section.key, section.content]),
  );
  return {
    ...sections,
    name: `${baseName} (${locale})`,
    event: source.event,
    locale,
    default: true,
  };
};
