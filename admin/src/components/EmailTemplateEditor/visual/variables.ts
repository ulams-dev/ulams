/**
 * Variable chips and locked shared parts for the visual e-mail editor (plan 4.4).
 *
 * The stored MJML never contains anything but plain tokens (`@VarUserName`, `${VarUserName}`,
 * `@GlobalSettingsMailFooter`): the server, the code view and the representability check all keep
 * reading that. The editor sees a decorated copy:
 *
 * - a registered variable inside an `mj-text` becomes a non-editable chip (`<span data-ulams-variable>`),
 * - a shared global-settings part that stands on its own in the layout (outside any text) becomes a
 *   locked `mj-raw` (`data-ulams-partial`), so the shared header or footer is never expanded into the
 *   template and cannot be changed from inside it.
 *
 * `toVisual` decorates before loading, `fromVisual` restores the plain tokens on export. Both work on
 * text only (no HTML parser: the browser one is the lossy part, see roundTrip.ts) and leave tags,
 * attributes, comments and every other element untouched, so a token in `href="@VarActionLink"` stays
 * a plain attribute value.
 */

export const CHIP_ATTR = 'data-ulams-variable';
export const PARTIAL_ATTR = 'data-ulams-partial';
export const DECORATIVE_ATTR = 'data-ulams-decorative';
export const CHIP_CLASS = 'ulams-chip';
export const PARTIAL_CLASS = 'ulams-partial';

/** Shared parts are the settings of the instance (`@GlobalSettings…`), edited in Settings. */
const PARTIAL_PATTERN = /@GlobalSettings[A-Za-z0-9_]+/g;
/** Elements that lay out other elements: a token standing alone in one of them is a shared part. */
const LAYOUT = new Set(['mj-body', 'mj-wrapper', 'mj-section', 'mj-column', 'mj-group', 'mj-hero']);

const escapeRegExp = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const escapeAttr = (value: string) =>
  value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
const unescapeAttr = (value: string) =>
  value
    .replace(/&lt;/g, '<')
    .replace(/&quot;/g, '"')
    .replace(/&amp;/g, '&');
const escapeText = (value: string) => value.replace(/&/g, '&amp;').replace(/</g, '&lt;');

export type PartialLabel = (token: string) => string;

const defaultLabel: PartialLabel = (token) => token;

const tokenPattern = (tokens: string[]): RegExp | undefined => {
  const forms = new Set<string>();
  tokens.forEach((token) => {
    if (!/^@[A-Za-z][A-Za-z0-9_]*$/.test(token)) return;
    forms.add(token);
    // The server also accepts `${VarName}` for every `@VarName`.
    forms.add('${' + token.slice(1) + '}');
  });
  if (forms.size === 0) return undefined;
  const alternatives = [...forms].sort((a, b) => b.length - a.length).map(escapeRegExp);
  // `@VarUser` must not match the start of `@VarUserName`.
  return new RegExp(`(?:${alternatives.join('|')})(?![A-Za-z0-9_])`, 'g');
};

export const chipHtml = (token: string): string =>
  `<span class="${CHIP_CLASS}" contenteditable="false" ${CHIP_ATTR}="${escapeAttr(
    token,
  )}">${escapeText(token)}</span>`;

export const partialHtml = (token: string, label: PartialLabel = defaultLabel): string =>
  `<mj-raw ${PARTIAL_ATTR}="${escapeAttr(token)}"><div class="${PARTIAL_CLASS}">${escapeText(
    label(token),
  )}</div></mj-raw>`;

const MJ_IMAGE = /<mj-image\b[^>]*>/g;

const TAG = /<!--[\s\S]*?-->|<(\/?)([A-Za-z][\w-]*)((?:"[^"]*"|'[^']*'|[^'">])*?)(\/?)>/g;

type Visit = (text: string, context: string | undefined) => string;

/** Calls `visit` for every piece of text with the innermost `mj-` element around it. */
const walk = (mjml: string, visit: Visit): string => {
  const stack: string[] = [];
  let out = '';
  let last = 0;
  TAG.lastIndex = 0;
  let match = TAG.exec(mjml);
  while (match) {
    out += visit(mjml.slice(last, match.index), stack[stack.length - 1]);
    out += match[0];
    last = match.index + match[0].length;
    const [, closing, name, , selfClosing] = match;
    const lower = name?.toLowerCase();
    if (lower?.startsWith('mj-')) {
      if (closing) {
        const at = stack.lastIndexOf(lower);
        if (at >= 0) stack.length = at;
      } else if (!selfClosing) {
        stack.push(lower);
      }
    }
    match = TAG.exec(mjml);
  }
  return out + visit(mjml.slice(last), stack[stack.length - 1]);
};

/** Tokens of shared parts that stand alone in the layout of `mjml`. */
export const lockedParts = (mjml: string): string[] => {
  const found: string[] = [];
  walk(mjml, (text, context) => {
    if (context && LAYOUT.has(context)) {
      (text.match(PARTIAL_PATTERN) ?? []).forEach((token) => found.push(token));
    }
    return text;
  });
  return [...new Set(found)];
};

/**
 * Decorates `mjml` for the editor. `variables` are the registered tokens (`@VarUserName`).
 * Idempotent: markup that is already decorated is left alone.
 */
export const toVisual = (
  mjml: string,
  variables: string[],
  label: PartialLabel = defaultLabel,
): string => {
  const chips = tokenPattern(variables);
  // The editor drops empty attributes, but `alt=""` is how an image says it is decorative.
  const marked = mjml.replace(MJ_IMAGE, (tag) =>
    tag.replace(/\salt=(?:""|'')/, ` ${DECORATIVE_ATTR}="true"`),
  );
  return walk(marked, (text, context) => {
    if (!text) return text;
    if (context === 'mj-text' && chips) {
      return text.replace(chips, (token) => chipHtml(token));
    }
    if (context && LAYOUT.has(context)) {
      return text.replace(PARTIAL_PATTERN, (token) => partialHtml(token, label));
    }
    return text;
  });
};

const CHIP = new RegExp(`<span\\b[^>]*\\b${CHIP_ATTR}="([^"]*)"[^>]*>[\\s\\S]*?<\\/span>`, 'g');
const PARTIAL = new RegExp(
  `<mj-raw\\b[^>]*\\b${PARTIAL_ATTR}="([^"]*)"[^>]*>[\\s\\S]*?<\\/mj-raw>`,
  'g',
);

/** Restores the plain tokens of the chips and shared parts: the inverse of {@link toVisual}. */
export const fromVisual = (mjml: string): string =>
  mjml
    .replace(MJ_IMAGE, (tag) =>
      // A decorative image keeps its explicit empty alt; a marker next to a real alt is dropped.
      tag.replace(new RegExp(`\\s${DECORATIVE_ATTR}="true"`), /\salt=/.test(tag) ? '' : ' alt=""'),
    )
    .replace(PARTIAL, (_all, token: string) => unescapeAttr(token))
    .replace(CHIP, (_all, token: string) => unescapeAttr(token));

/** Whether `content` uses `token` in the `@Var` or the `${Var}` form (the rule of the server). */
export const usesVariable = (content: string, token: string): boolean => {
  if (!/^@[A-Za-z][A-Za-z0-9_]*$/.test(token)) return content.includes(token);
  const plain = new RegExp(`${escapeRegExp(token)}(?![A-Za-z0-9_])`);
  return plain.test(content) || content.includes('${' + token.slice(1) + '}');
};

/** Required variables the content does not use yet, for the live list of the variables panel. */
export const missingRequired = (content: string, required: string[]): string[] =>
  required.filter((token) => !usesVariable(content, token));
