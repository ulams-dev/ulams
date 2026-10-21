export type InsertResult = 'inserted' | 'no-target';

export type VisualEditorHandle = {
  /** Puts the chip of `token` into the selected text block. */
  insertVariable: (token: string) => InsertResult;
};
