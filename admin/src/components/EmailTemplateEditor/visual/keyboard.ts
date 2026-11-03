import type { Component, Editor } from 'grapesjs';
import { PARTIAL_TYPE } from './ulams';

/**
 * Keyboard alternative to drag and drop (plan 4.8, M5): the model operations behind the structure
 * tree and the toolbar of the visual editor. Everything here works on the GrapesJS component tree,
 * never on the DOM, so it behaves the same with the mouse, the keyboard and a screen reader.
 */

export const SECTION_BLOCKS = ['mj-1-column', 'mj-2-columns', 'mj-3-columns'];
export const CONTENT_BLOCKS = ['mj-text', 'mj-button', 'mj-image', 'mj-divider', 'mj-spacer'];

const type = (component: Component): string => String(component.get('type') ?? '');

/** Components the author can select: MJML elements and the locked shared parts. */
const isStructural = (component: Component): boolean =>
  type(component).startsWith('mj-') || type(component) === PARTIAL_TYPE;

const structuralChildren = (component: Component): Component[] =>
  component.components().models.filter(isStructural);

export const bodyOf = (editor: Editor): Component | undefined => {
  const find = (component: Component): Component | undefined => {
    if (type(component) === 'mj-body') return component;
    return component
      .components()
      .models.map(find)
      .find((found) => !!found);
  };
  return find(editor.getWrapper() as Component);
};

export type OutlineItem = {
  component: Component;
  /** Stable key of the component for the lifetime of the editor. */
  id: string;
  level: number;
  /** Position among the visible siblings, 1 based. */
  position: number;
  siblings: number;
  hasChildren: boolean;
  type: string;
  locked: boolean;
};

export const idOf = (component: Component): string => String((component as { cid?: string }).cid);

/** Flat list of the visible items of the tree (collapsed branches skipped), in document order. */
export const outline = (editor: Editor, collapsed: ReadonlySet<string>): OutlineItem[] => {
  const body = bodyOf(editor);
  if (!body) return [];
  const items: OutlineItem[] = [];
  const visit = (component: Component, level: number, position: number, siblings: number) => {
    const children = structuralChildren(component);
    const id = idOf(component);
    items.push({
      component,
      id,
      level,
      position,
      siblings,
      hasChildren: children.length > 0,
      type: type(component),
      locked: component.get('removable') === false,
    });
    if (!collapsed.has(id)) {
      children.forEach((child, i) => visit(child, level + 1, i + 1, children.length));
    }
  };
  visit(body, 1, 1, 1);
  return items;
};

/** Text of a component without markup, chips written as their token. */
export const plainText = (component: Component): string => {
  const html = component.toHTML?.() ?? '';
  return html
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
};

const fileName = (component: Component): string => {
  const src = String(component.getAttributes().src ?? '');
  if (!src || src.startsWith('data:')) return '';
  return src.split('?')[0].split('/').pop() ?? '';
};

/** The element the author sees in the tree: its kind and a short excerpt of its content. */
export const describe = (
  component: Component,
  kindName: string,
): { kind: string; excerpt: string } => {
  const t = type(component);
  let excerpt = '';
  if (t === 'mj-text' || t === 'mj-button') excerpt = plainText(component);
  if (t === 'mj-image') {
    excerpt = String(component.getAttributes().alt ?? '') || fileName(component);
  }
  if (t === PARTIAL_TYPE) excerpt = String(component.getAttributes()['data-ulams-partial'] ?? '');
  return { kind: kindName, excerpt: excerpt.length > 48 ? `${excerpt.slice(0, 47)}…` : excerpt };
};

const ancestorUnder = (component: Component, parent: Component): Component | undefined => {
  let current: Component | undefined = component;
  while (current && current.parent() !== parent) current = current.parent();
  return current;
};

const columnsOf = (body: Component): Component[] => {
  const columns: Component[] = [];
  const walk = (component: Component) =>
    component.components().models.forEach((child) => {
      if (type(child) === 'mj-column') columns.push(child);
      else walk(child);
    });
  walk(body);
  return columns;
};

export type Placement = { parent: Component; at: number };

/**
 * Where a block goes, given the selection: a section after the section that holds the selection, a
 * content block after the selected content block, into the selected column or into the first column
 * of the selected section. Without a selection: at the end. `undefined` when nothing can hold it.
 */
export const placementFor = (editor: Editor, blockId: string): Placement | undefined => {
  const body = bodyOf(editor);
  if (!body) return undefined;
  const selected = editor.getSelected();
  const inBody = selected && selected !== body ? selected : undefined;

  if (SECTION_BLOCKS.includes(blockId)) {
    const anchor = inBody ? ancestorUnder(inBody, body) : undefined;
    return { parent: body, at: anchor ? anchor.index() + 1 : body.components().length };
  }

  if (inBody) {
    if (type(inBody) === 'mj-column') {
      return { parent: inBody, at: inBody.components().length };
    }
    const holder = inBody.parent();
    if (holder && type(holder) === 'mj-column') {
      return { parent: holder, at: inBody.index() + 1 };
    }
    const section = ancestorUnder(inBody, body);
    const column = section && columnsOf(section)[0];
    if (column) return { parent: column, at: column.components().length };
  }
  const last = columnsOf(body).pop();
  return last ? { parent: last, at: last.components().length } : undefined;
};

export type AddResult =
  | { ok: true; component: Component; parent: Component }
  | { ok: false; reason: 'unknown-block' | 'no-place' };

/** Adds the block `blockId` where `placementFor` says and selects it. */
export const addBlock = (editor: Editor, blockId: string): AddResult => {
  const block = editor.Blocks.get(blockId);
  if (!block) return { ok: false, reason: 'unknown-block' };
  let place = placementFor(editor, blockId);
  if (!place && !SECTION_BLOCKS.includes(blockId)) {
    // A content block needs a column: make a one column section first.
    const body = bodyOf(editor);
    const section = body && body.append(editor.Blocks.get('mj-1-column')?.getContent() as never);
    const column = section && columnsOf(body as Component).pop();
    if (column) place = { parent: column, at: 0 };
  }
  if (!place) return { ok: false, reason: 'no-place' };
  const added = place.parent.append(block.getContent() as never, { at: place.at });
  const component = added[0];
  if (!component) return { ok: false, reason: 'no-place' };
  editor.select(component, { scroll: { behavior: 'auto', block: 'nearest' } } as never);
  return { ok: true, component, parent: place.parent };
};

export type MoveResult =
  | { ok: true; into?: Component }
  | { ok: false; reason: 'locked' | 'first' | 'last' };

/**
 * Moves a block one step up or down among its siblings. A content block at the edge of a column
 * continues into the neighbouring column (in document order), so every position is reachable.
 */
export const moveBlock = (editor: Editor, component: Component, direction: -1 | 1): MoveResult => {
  const parent = component.parent();
  if (!parent || component.get('draggable') === false) return { ok: false, reason: 'locked' };
  const siblings = parent.components().models;
  const index = component.index();
  const target = index + direction;
  if (target >= 0 && target < siblings.length) {
    component.move(parent, { at: direction === 1 ? target + 1 : target });
    editor.select(component);
    return { ok: true };
  }
  const body = bodyOf(editor);
  if (body && type(parent) === 'mj-column') {
    const columns = columnsOf(body);
    const neighbour = columns[columns.indexOf(parent) + direction];
    if (neighbour) {
      component.move(neighbour, {
        at: direction === 1 ? 0 : neighbour.components().length,
      });
      editor.select(component);
      return { ok: true, into: neighbour };
    }
  }
  return { ok: false, reason: direction === -1 ? 'first' : 'last' };
};

export type RemoveResult = { ok: true; next?: Component } | { ok: false; reason: 'locked' };

/** Removes a block and returns the component that should take the selection. */
export const removeBlock = (editor: Editor, component: Component): RemoveResult => {
  if (component.get('removable') === false) return { ok: false, reason: 'locked' };
  const parent = component.parent();
  const siblings = parent ? structuralChildren(parent) : [];
  const index = siblings.indexOf(component);
  const next = siblings[index + 1] ?? siblings[index - 1] ?? parent;
  component.remove();
  if (next) editor.select(next);
  return { ok: true, next };
};

/** Starts editing the text of a text block in the canvas (the keyboard way of a double click). */
export const startTextEdit = (editor: Editor, component: Component): boolean => {
  if (type(component) !== 'mj-text') return false;
  editor.select(component);
  const view = component.getView() as unknown as {
    onActive?: (event?: unknown) => unknown;
    getChildrenContainer?: () => HTMLElement;
    el: HTMLElement;
  };
  if (!view?.onActive) return false;
  const result = view.onActive({ stopPropagation() {} });
  const focus = () => {
    const target = view.getChildrenContainer?.() ?? view.el;
    target.focus();
  };
  Promise.resolve(result).then(focus, focus);
  return true;
};

/** Ends the text editing started by `startTextEdit` and keeps what was typed. */
export const stopTextEdit = (component: Component): void => {
  const view = component.getView() as unknown as { disableEditing?: () => void };
  view?.disableEditing?.();
};
