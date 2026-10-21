import type { Component, Editor } from 'grapesjs';
import type { InsertResult } from './handle';
import {
  CHIP_ATTR,
  CHIP_CLASS,
  DECORATIVE_ATTR,
  PARTIAL_ATTR,
  PARTIAL_CLASS,
  chipHtml,
  partialHtml,
} from './variables';

/**
 * The GrapesJS part of the M4 features (plan 4.4, 4.10): variable chips, locked shared parts, the
 * variable pickers of the link fields and the image dialog that replaces the built-in asset manager.
 * Everything that is plain text processing lives in `variables.ts`.
 */

export type ImagePick = { url: string; alt: string };

export type UlamsOptions = {
  /** Registered variables (`@VarUserName`), event variables first. */
  variables: string[];
  /**
   * Opens the image dialog (upload to the tenant storage, required alt text). Resolves with the
   * chosen image, or `undefined` when the author cancelled.
   */
  pickImage?: () => Promise<ImagePick | undefined>;
};

export const CHIP_TYPE = 'ulams-variable';
export const PARTIAL_TYPE = 'ulams-partial';

/** Style of the chips and the locked parts inside the canvas. Colours reach 4.5:1 on their ground. */
export const CANVAS_CSS = `
.${CHIP_CLASS} { display: inline-block; padding: 0 6px; border-radius: 10px; background: #e6f4ff;
  color: #003a8c; border: 1px solid #1677ff; font-family: monospace; font-size: 0.9em; user-select: all; }
.${PARTIAL_CLASS} { padding: 12px 16px; border: 2px dashed #595959; background: #f5f5f5;
  color: #262626; font-family: Arial, sans-serif; font-size: 13px; text-align: center; }
`;

/** Replaces `{name}` placeholders of an editor message. */
export const format = (message: string, params: Record<string, string>): string =>
  message.replace(/\{(\w+)\}/g, (all, name: string) => params[name] ?? all);

const t = (editor: Editor, key: string, params: Record<string, string> = {}): string =>
  format(String(editor.I18n.t(`ulams.${key}`) ?? key), params);

export const partialLabel = (editor: Editor) => (token: string) => t(editor, 'partial', { token });

/** The shared part cannot be changed, moved, copied or removed from inside a template. */
const LOCKED = {
  removable: false,
  draggable: false,
  copyable: false,
  droppable: false,
  editable: false,
  highlightable: false,
  resizable: false,
  stylable: false,
  badgable: true,
  layerable: true,
  traits: [] as string[],
};

const LOCKED_CHILD = { ...LOCKED, selectable: false, hoverable: false, layerable: false };

const registerTypes = (editor: Editor) => {
  const isElement = (el: unknown): el is Element =>
    !!el && (el as Element).nodeType === 1 && typeof (el as Element).hasAttribute === 'function';

  // Added after grapesjs-mjml: the last registered type is asked first.
  editor.Components.addType(CHIP_TYPE, {
    isComponent: (el) =>
      isElement(el) && el.hasAttribute(CHIP_ATTR) ? { type: CHIP_TYPE } : false,
    model: {
      defaults: {
        tagName: 'span',
        name: 'Variable',
        droppable: false,
        editable: false,
        highlightable: false,
        stylable: false,
        traits: [],
      },
      // Always the same markup, whatever happened to the text inside the chip.
      toHTML() {
        return chipHtml(String((this as Component).getAttributes()[CHIP_ATTR] ?? ''));
      },
    },
  });

  editor.Components.addType(PARTIAL_TYPE, {
    extend: 'mj-raw',
    isComponent: (el) =>
      isElement(el) && el.hasAttribute(PARTIAL_ATTR) ? { type: PARTIAL_TYPE } : false,
    model: {
      defaults: { ...LOCKED, name: 'Shared part' },
      init() {
        // The label inside is decoration: nothing in it can be selected or edited.
        const lock = () =>
          (this as Component).onAll((child) => {
            if (child !== (this as Component)) child.set(LOCKED_CHILD as never);
          });
        lock();
        (this as Component).components().on('add', lock);
      },
      toHTML() {
        const token = String((this as Component).getAttributes()[PARTIAL_ATTR] ?? '');
        return partialHtml(token, partialLabel(editor));
      },
    },
  });
};

/** `variable` select of a link field: choosing a token writes it into the attribute. */
const linkVariableTrait = (editor: Editor, variables: string[], attribute: string) => ({
  type: 'select',
  name: `ulams-${attribute}-variable`,
  label: t(editor, `${attribute}_variable`),
  changeProp: true,
  options: [
    { id: '', name: t(editor, 'variable_none') },
    ...variables.map((token) => ({ id: token, name: token })),
  ],
});

const registerLinkPickers = (editor: Editor, variables: string[]) => {
  ['mj-button', 'mj-image'].forEach((type) => {
    const attribute = 'href';
    const existing = editor.Components.getType(type);
    if (!existing) return;
    const defaults = existing.model.prototype.defaults ?? {};
    const extra: unknown[] = [linkVariableTrait(editor, variables, attribute)];
    if (type === 'mj-image') {
      extra.push({
        type: 'button',
        name: 'ulams-choose-image',
        text: t(editor, 'choose_image'),
        full: true,
        command: (ed: Editor) => ed.runCommand('open-assets', { target: ed.getSelected() }),
      });
    }
    editor.Components.addType(type, {
      model: { defaults: { traits: [...(defaults.traits ?? []), ...extra] } },
    });
  });

  editor.on('component:update:ulams-href-variable', (component: Component) => {
    const token = component.get('ulams-href-variable' as never) as string | undefined;
    if (!token) return;
    // The picker only writes the attribute and goes back to "none".
    component.set('ulams-href-variable' as never, '' as never, { silent: true });
    component.addAttributes({ href: token });
  });
};

export type ImageTarget = Pick<Component, 'addAttributes' | 'set'> &
  Partial<Pick<Component, 'removeAttributes'>>;

/** Puts the chosen image into an `mj-image`: its public URL and the alt text (empty for decorative). */
export const applyImage = (target: ImageTarget, image: ImagePick): void => {
  target.set({ src: image.url } as never);
  // An empty alt is dropped by the editor; the marker is turned back into alt="" on export.
  const attributes: Record<string, string> = { src: image.url, alt: image.alt };
  if (image.alt === '') {
    delete attributes.alt;
    attributes[DECORATIVE_ATTR] = 'true';
    target.removeAttributes?.('alt');
  } else {
    target.removeAttributes?.(DECORATIVE_ATTR);
  }
  target.addAttributes(attributes);
};

const registerImageDialog = (editor: Editor, options: UlamsOptions) => {
  // The built-in command (asset manager) is replaced; its typed overload is that of the built-in class.
  editor.Commands.add('open-assets', {
    run(ed: Editor, _sender: unknown, opts: { target?: Component } = {}) {
      const target = opts.target ?? ed.getSelected();
      if (!target || !options.pickImage) return;
      options.pickImage().then((image) => {
        if (image) applyImage(target, image);
      });
    },
    stop() {},
  } as never);
};

const injectCanvasStyle = (editor: Editor) => {
  const inject = () => {
    const doc = editor.Canvas.getDocument();
    if (!doc || doc.getElementById('ulams-canvas-style')) return;
    const style = doc.createElement('style');
    style.id = 'ulams-canvas-style';
    style.textContent = CANVAS_CSS;
    doc.head.appendChild(style);
  };
  editor.on('canvas:frame:load', inject);
  editor.on('load', inject);
};

/** Plugin of the Ulams additions. Added after grapesjs-mjml, which defines the types it extends. */
export const ulamsPlugin = (editor: Editor, options: UlamsOptions) => {
  registerTypes(editor);
  registerLinkPickers(editor, options.variables);
  registerImageDialog(editor, options);
  injectCanvasStyle(editor);
};

/**
 * Inserts the chip of `token` where the author is: at the caret when a text block is being edited,
 * otherwise at the end of the selected text block. `no-target` when no text block is selected.
 */
export const insertVariable = (editor: Editor, token: string): InsertResult => {
  const selected = editor.getSelected();
  if (!selected || selected.get('type') !== 'mj-text') return 'no-target';
  const view = selected.view as unknown as {
    rteEnabled?: boolean;
    activeRte?: { insertHTML: (html: string) => void };
  };
  if (view?.rteEnabled && view.activeRte) {
    try {
      view.activeRte.insertHTML(chipHtml(token));
      return 'inserted';
    } catch {
      // No caret in the block: fall through to appending.
    }
  }
  selected.append({
    type: CHIP_TYPE,
    attributes: { [CHIP_ATTR]: token, class: CHIP_CLASS, contenteditable: 'false' },
    components: [{ type: 'textnode', content: token }],
  });
  return 'inserted';
};
