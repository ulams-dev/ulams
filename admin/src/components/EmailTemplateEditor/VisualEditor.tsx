import type { Editor } from 'grapesjs';
import React, { useEffect, useRef } from 'react';
import { parseMarkup, treeLines } from './roundTrip';
import { createEditor, exportMjml } from './visual/engine';

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
};

/**
 * GrapesJS with grapesjs-mjml in a fixed-height box. One editor instance per mount, destroyed on
 * unmount so the canvas iframe does not leak. The storage manager is off: the form is the store.
 */
const VisualEditor: React.FC<VisualEditorProps> = ({ initial, onChange, locale, label }) => {
  const box = useRef<HTMLDivElement | null>(null);
  const handler = useRef(onChange);
  handler.current = onChange;

  useEffect(() => {
    if (!box.current) return undefined;
    const editor: Editor = createEditor(box.current, initial, locale);
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
      if (timer) clearTimeout(timer);
      editor.destroy();
    };
    // The editor is created once per mount, later changes of `initial` are intentionally ignored.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div ref={box} role="region" aria-label={label} className="email-template-editor__visual" />
  );
};

export default VisualEditor;
