import type { Editor } from 'grapesjs';
import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';
import KeyboardPanel from './KeyboardPanel';
import { parseMarkup, treeLines } from './roundTrip';
import { createEditor, exportMjml, type VisualOptions } from './visual/engine';
import type { VisualEditorHandle } from './visual/handle';
import { insertVariable } from './visual/ulams';

export const CHANGE_DELAY_MS = 300;

/** Content of an MJML text without its formatting, to tell a real edit from a re-export. */
const signature = (mjml: string) => treeLines(parseMarkup(mjml)).join('\n');

export type VisualEditorProps = {
  /** MJML that passed the representability check. Read once, when the editor is created. */
  initial: string;
  /** Called with the exported MJML after the author changed something (never on load). */
  onChange: (mjml: string) => void;
  locale?: string;
  label: string;
  /** Registered variables: they open as chips and fill the link pickers. */
  variables?: string[];
  /** Opens the image dialog, see `VisualOptions`. */
  pickImage?: VisualOptions['pickImage'];
};

/**
 * GrapesJS with grapesjs-mjml in a fixed-height box. One editor instance per mount, destroyed on
 * unmount so the canvas iframe does not leak. The storage manager is off: the form is the store.
 */
const VisualEditor = forwardRef<VisualEditorHandle, VisualEditorProps>(function VisualEditor(
  { initial, onChange, locale, label, variables, pickImage },
  ref,
) {
  const box = useRef<HTMLDivElement | null>(null);
  const instance = useRef<Editor | null>(null);
  const [current, setCurrent] = useState<Editor | null>(null);
  const handler = useRef(onChange);
  handler.current = onChange;
  const picker = useRef(pickImage);
  const labelRef = useRef(label);
  labelRef.current = label;
  picker.current = pickImage;

  useImperativeHandle(ref, () => ({
    insertVariable: (token) =>
      instance.current ? insertVariable(instance.current, token) : 'no-target',
  }));

  useEffect(() => {
    if (!box.current) return undefined;
    const editor: Editor = createEditor(box.current, initial, locale, {
      variables,
      pickImage: () => (picker.current ? picker.current() : Promise.resolve(undefined)),
    });
    instance.current = editor;
    setCurrent(editor);
    // The canvas is an iframe: it needs a name for screen readers (WCAG 4.1.2).
    const nameFrame = () => editor.Canvas.getFrameEl()?.setAttribute('title', labelRef.current);
    editor.on('canvas:frame:load', nameFrame);
    editor.on('load', nameFrame);
    let baseline: string | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let active = true;

    editor.onReady(() => {
      baseline = signature(exportMjml(editor));
      editor.on('update', () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
          if (!active || baseline === undefined) return;
          const next = exportMjml(editor);
          // Loading and re-rendering rewrite the text; only a real edit may reach the form.
          const nextSignature = signature(next);
          if (nextSignature !== baseline) {
            baseline = nextSignature;
            handler.current(next);
          }
        }, CHANGE_DELAY_MS);
      });
    });

    return () => {
      active = false;
      instance.current = null;
      setCurrent(null);
      if (timer) clearTimeout(timer);
      editor.destroy();
    };
    // The editor is created once per mount, later changes of `initial` are intentionally ignored.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <>
      {current && (
        <KeyboardPanel
          editor={current}
          chooseImage={(component) => current.runCommand('open-assets', { target: component })}
        />
      )}
      <div ref={box} role="region" aria-label={label} className="email-template-editor__visual" />
    </>
  );
});

export default VisualEditor;
