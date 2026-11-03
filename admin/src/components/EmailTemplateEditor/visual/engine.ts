import grapesjs, { type Editor, type EditorConfig } from 'grapesjs';
import mjmlPlugin from 'grapesjs-mjml';
import 'grapesjs/dist/css/grapes.min.css';
import { editorLocale, editorMessages, partialMessage } from './locales';
import { format, ulamsPlugin, type ImagePick } from './ulams';
import { fromVisual, toVisual } from './variables';

/**
 * The only module that imports GrapesJS. It is loaded with `import()` from the e-mail template
 * page, so the editor (about 660 KB gzip, budget 700 KB, see `bundleBudget.test.ts`) is a separate
 * chunk and never part of the main admin bundle.
 */

/** 1x1 transparent GIF: the default placeholder points at a third-party image host. */
const IMAGE_PLACEHOLDER =
  'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

/**
 * Blocks without a built-in remote image (social icons, hero, carousel use third-party URLs) and
 * only elements that every template of the package can render.
 */
export const BLOCKS = [
  'mj-1-column',
  'mj-2-columns',
  'mj-3-columns',
  'mj-text',
  'mj-button',
  'mj-image',
  'mj-divider',
  'mj-spacer',
];

export const EMPTY_TEMPLATE = '<mjml><mj-body></mj-body></mjml>';

export type VisualOptions = {
  /** Registered variables (`@VarUserName`): they open as chips. */
  variables?: string[];
  pickImage?: () => Promise<ImagePick | undefined>;
};

export const buildConfig = (
  mjml: string,
  adminLocale: string | undefined,
  extra: Partial<EditorConfig> = {},
  options: VisualOptions = {},
): EditorConfig => ({
  fromElement: false,
  storageManager: false,
  // GrapesJS sends anonymous usage data to app.grapesjs.com unless told not to: no telemetry (plan 4.10).
  telemetry: false,
  // GrapesJS loads Font Awesome 4.7 from cdnjs by default; the editor must work offline.
  cssIcons: '',
  // Chips and locked shared parts are a decoration of the stored text, undone by exportMjml.
  components: toVisual(mjml, options.variables ?? [], (token) =>
    format(partialMessage(adminLocale), { token }),
  ),
  plugins: [
    (editor: Editor) =>
      mjmlPlugin(editor, {
        blocks: BLOCKS,
        imagePlaceholderSrc: IMAGE_PLACEHOLDER,
      }),
    (editor: Editor) =>
      ulamsPlugin(editor, { variables: options.variables ?? [], pickImage: options.pickImage }),
  ],
  i18n: {
    // Without this the locale falls back to `en` whatever is configured.
    detectLocale: false,
    locale: editorLocale(adminLocale),
    localeFallback: 'en',
    messages: editorMessages,
  },
  // The canvas iframe is same-origin: drop scripts and unsafe attributes before loading (plan 4.7).
  parser: {
    optionsHtml: { allowScripts: false, allowUnsafeAttr: false, allowUnsafeAttrValue: false },
  },
  ...extra,
});

/** The import modal would feed unchecked MJML into the canvas and skip the representability check. */
const removeImportButton = (editor: Editor) => {
  editor.Panels.getPanels().forEach((panel) => {
    const buttons = panel.buttons;
    buttons
      .filter((button) => String(button.id).includes('import'))
      .forEach((button) => buttons.remove(button));
  });
};

export const createEditor = (
  container: HTMLElement,
  mjml: string,
  adminLocale: string | undefined,
  options: VisualOptions = {},
): Editor => {
  const editor = grapesjs.init({
    ...buildConfig(mjml, adminLocale, {}, options),
    container,
    height: '70vh',
  });
  removeImportButton(editor);
  return editor;
};

/** MJML text of the editor, as the plugin exports it. */
export const exportMjml = (editor: Editor): string => fromVisual(editor.getHtml());

/**
 * Loads `mjml` into a throwaway headless editor (no canvas, no panels), exports it and destroys it:
 * the check of plan 4.6. Components are parsed by the same model code as in the visible editor,
 * which is where the markup is lost or moved.
 */
export const exportOnce = async (
  mjml: string,
  adminLocale?: string,
  options: VisualOptions = {},
): Promise<string> => {
  const editor = grapesjs.init({
    ...buildConfig(mjml, adminLocale, {}, options),
    headless: true,
  });
  try {
    return exportMjml(editor);
  } finally {
    editor.destroy();
  }
};
