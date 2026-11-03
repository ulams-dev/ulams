import type { Component, Editor } from 'grapesjs';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useIntl } from 'umi';
import {
  CONTENT_BLOCKS,
  SECTION_BLOCKS,
  addBlock,
  describe,
  idOf,
  moveBlock,
  outline,
  removeBlock,
  startTextEdit,
  stopTextEdit,
  type OutlineItem,
} from './visual/keyboard';
import { fieldsFor } from './visual/styleFields';

/**
 * Keyboard and screen reader alternative to drag and drop (plan 4.8, M5): a structure tree (roving
 * tabindex, arrow keys), block actions (add, move, remove, edit text, undo) and a properties form.
 * Everything acts on the same GrapesJS model as the canvas, so both views stay in step and the
 * stored MJML is the one the canvas would produce.
 */

const BLOCKS = [...SECTION_BLOCKS, ...CONTENT_BLOCKS];

/** Events after which the tree, the toolbar and the form are drawn again. */
const REFRESH = [
  'component:selected',
  'component:deselected',
  'component:add',
  'component:remove',
  'component:update',
  'component:styleUpdate',
  'undo',
  'redo',
  'load',
].join(' ');

type Intl = ReturnType<typeof useIntl>;

const hasMessage = (intl: Intl, id: string): boolean => !!intl.messages?.[id];

const kindOf = (intl: Intl, component: Component): string => {
  const type = String(component.get('type'));
  const id = `templates.kb_kind_${type}`;
  return hasMessage(intl, id) ? intl.formatMessage({ id }) : String(component.getName?.() ?? type);
};

type FieldProps = {
  id: string;
  label: string;
  value: string;
  kind: 'text' | 'select' | 'color' | 'checkbox';
  options?: { value: string; label: string }[];
  onCommit: (value: string) => void;
};

/** One labelled control; text is committed on Enter and on blur, a choice at once. */
const Field: React.FC<FieldProps> = ({ id, label, value, kind, options, onCommit }) => {
  const [draft, setDraft] = useState(value);
  const choices =
    options && value !== '' && !options.some((option) => option.value === value)
      ? [...options, { value, label: value }]
      : options;
  useEffect(() => setDraft(value), [value]);
  const commit = () => {
    if (draft !== value) onCommit(draft);
  };
  return (
    <div className="email-template-editor__kb-field">
      <label htmlFor={id}>{label}</label>
      {kind === 'select' ? (
        <select id={id} value={draft} onChange={(e) => onCommit(e.target.value)}>
          {choices?.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      ) : kind === 'checkbox' ? (
        <input
          id={id}
          type="checkbox"
          checked={value === 'true'}
          onChange={(e) => onCommit(String(e.target.checked))}
        />
      ) : (
        <input
          id={id}
          type="text"
          value={draft}
          autoComplete="off"
          onChange={(e) => setDraft(e.target.value)}
          onBlur={commit}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              commit();
            }
          }}
        />
      )}
    </div>
  );
};

type PropertiesProps = {
  editor: Editor;
  component: Component | undefined;
  kind: string;
  onChanged: (label: string) => void;
};

const TRAIT_SKIP = ['id', 'title'];

const PropertiesForm: React.FC<PropertiesProps> = ({ editor, component, kind, onChanged }) => {
  const intl = useIntl();
  if (!component) {
    return (
      <div className="email-template-editor__kb-props" data-kb-properties>
        <p className="email-template-editor__hint">
          {intl.formatMessage({ id: 'templates.kb_props_none' })}
        </p>
      </div>
    );
  }
  const cid = idOf(component);
  const label = (id: string, fallback: string) =>
    hasMessage(intl, id) ? intl.formatMessage({ id }) : fallback;

  const traits = (component.getTraits?.() ?? []).filter(
    (trait) => !TRAIT_SKIP.includes(String(trait.getName())),
  );
  const style = component.getStyle() as Record<string, string>;
  const fields = fieldsFor(component.get('stylable'));

  const none = intl.formatMessage({ id: 'templates.kb_none' });
  const locked = component.get('removable') === false && component.get('editable') === false;

  return (
    <div className="email-template-editor__kb-props" data-kb-properties data-testid="kb-properties">
      <h5 id="email-editor-kb-props">
        {intl.formatMessage({ id: 'templates.kb_props' }, { name: kind })}
      </h5>
      {locked && (
        <p className="email-template-editor__hint">
          {intl.formatMessage({ id: 'templates.kb_locked_hint' })}
        </p>
      )}
      {traits.length > 0 && (
        <fieldset>
          <legend>{intl.formatMessage({ id: 'templates.kb_group_content' })}</legend>
          {traits.map((trait) => {
            const name = String(trait.getName());
            const type = String(trait.get('type') ?? 'text');
            const text = label(`templates.kb_trait_${name}`, String(trait.getLabel?.() ?? name));
            if (type === 'button') {
              const command = trait.get('command') as unknown;
              return (
                <button
                  key={`${cid}-${name}`}
                  type="button"
                  className="email-template-editor__kb-button"
                  onClick={() => {
                    if (typeof command === 'function') command(editor, trait);
                  }}
                >
                  {String(trait.get('text') ?? text)}
                </button>
              );
            }
            const rawOptions =
              (trait.get('options') as {
                id?: string;
                value?: string;
                name?: string;
                label?: string;
              }[]) ?? [];
            const options = rawOptions.map((option) => ({
              value: String(option.id ?? option.value ?? ''),
              label: String(option.name ?? option.label ?? option.id ?? option.value ?? ''),
            }));
            return (
              <Field
                key={`${cid}-${name}`}
                id={`kb-trait-${cid}-${name}`}
                label={text}
                kind={type === 'select' ? 'select' : type === 'checkbox' ? 'checkbox' : 'text'}
                options={options}
                value={String(trait.getValue() ?? '')}
                onCommit={(value) => {
                  trait.setValue(type === 'checkbox' ? value === 'true' : value);
                  onChanged(text);
                }}
              />
            );
          })}
        </fieldset>
      )}
      {fields.length > 0 && (
        <fieldset>
          <legend>{intl.formatMessage({ id: 'templates.kb_group_style' })}</legend>
          {fields.map((field) => {
            const text = label(`templates.kb_style_${field.property}`, field.property);
            return (
              <Field
                key={`${cid}-${field.property}`}
                id={`kb-style-${cid}-${field.property}`}
                label={text}
                kind={field.kind === 'select' ? 'select' : 'text'}
                options={
                  field.options
                    ? [
                        { value: '', label: none },
                        ...field.options.map((value) => ({ value, label: value })),
                      ]
                    : undefined
                }
                value={String(style[field.property] ?? '')}
                onCommit={(value) => {
                  component.addStyle({ [field.property]: value });
                  onChanged(text);
                }}
              />
            );
          })}
        </fieldset>
      )}
    </div>
  );
};

export type KeyboardPanelProps = {
  editor: Editor;
  /** Opens the image dialog for the selected image block. */
  chooseImage: (component: Component) => void;
};

const KeyboardPanel: React.FC<KeyboardPanelProps> = ({ editor, chooseImage }) => {
  const intl = useIntl();
  const [, setVersion] = useState(0);
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(new Set());
  const [status, setStatus] = useState('');
  const [blockId, setBlockId] = useState(BLOCKS[3]);
  const tree = useRef<HTMLDivElement | null>(null);
  const toolbar = useRef<HTMLDivElement | null>(null);
  const focusAfter = useRef<string | undefined>();
  const editing = useRef<{ component: Component; stop: () => void } | undefined>();

  useEffect(() => {
    const refresh = () => setVersion((v) => v + 1);
    editor.on(REFRESH, refresh);
    refresh();
    return () => {
      editor.off(REFRESH, refresh);
    };
  }, [editor]);

  const items = outline(editor, collapsed);
  const selected = editor.getSelected();
  const selectedItem = items.find((item) => item.component === selected);
  // One tab stop in the tree: the selected block, else the first one.
  const activeId = selectedItem?.id ?? items[0]?.id;

  const announce = useCallback((message: string) => {
    // The same text twice would not be read again: a trailing space makes it a new node value.
    setStatus((previous) => (previous === message ? `${message} ` : message));
  }, []);

  // Moves the focus to a tree item after the tree was drawn again.
  useEffect(() => {
    const id = focusAfter.current;
    if (!id) return;
    const element = tree.current?.querySelector<HTMLElement>(`[data-id="${id}"]`);
    if (element) {
      focusAfter.current = undefined;
      element.focus();
    }
  });

  const position = (component: Component) => {
    const siblings = component
      .parent()
      ?.components()
      .models.filter((c) => String(c.get('type')).startsWith('mj-')) ?? [component];
    return { position: siblings.indexOf(component) + 1, total: siblings.length };
  };

  const selectItem = (item: OutlineItem) => {
    if (editor.getSelected() !== item.component) {
      editor.select(item.component, { scroll: { behavior: 'auto', block: 'nearest' } } as never);
    }
  };

  const endEditing = useCallback(() => {
    editing.current?.stop();
    editing.current = undefined;
  }, []);

  useEffect(() => {
    editor.on('component:deselected', endEditing);
    return () => {
      editor.off('component:deselected', endEditing);
      endEditing();
    };
  }, [editor, endEditing]);

  const beginEdit = (component: Component) => {
    endEditing();
    if (!startTextEdit(editor, component)) {
      announce(intl.formatMessage({ id: 'templates.kb_not_text' }));
      return;
    }
    const doc = editor.Canvas.getDocument();
    const onKey = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return;
      event.preventDefault();
      event.stopPropagation();
      endEditing();
      focusAfter.current = idOf(component);
      setVersion((v) => v + 1);
      announce(intl.formatMessage({ id: 'templates.kb_edit_done' }));
    };
    doc?.addEventListener('keydown', onKey, true);
    editing.current = {
      component,
      stop: () => {
        doc?.removeEventListener('keydown', onKey, true);
        stopTextEdit(component);
      },
    };
    announce(intl.formatMessage({ id: 'templates.kb_editing' }));
  };

  const onAdd = () => {
    const result = addBlock(editor, blockId);
    if (!result.ok) {
      announce(intl.formatMessage({ id: 'templates.kb_no_place' }));
      return;
    }
    const { position: at, total } = position(result.component);
    focusAfter.current = idOf(result.component);
    announce(
      intl.formatMessage(
        { id: 'templates.kb_added' },
        { kind: kindOf(intl, result.component), position: at, total },
      ),
    );
    setVersion((v) => v + 1);
  };

  const onMove = (component: Component, direction: -1 | 1) => {
    const kind = kindOf(intl, component);
    const result = moveBlock(editor, component, direction);
    if (!result.ok) {
      announce(
        intl.formatMessage(
          {
            id:
              result.reason === 'locked'
                ? 'templates.kb_locked'
                : result.reason === 'first'
                ? 'templates.kb_first'
                : 'templates.kb_last',
          },
          { kind },
        ),
      );
      return;
    }
    const { position: at, total } = position(component);
    focusAfter.current = idOf(component);
    announce(intl.formatMessage({ id: 'templates.kb_moved' }, { kind, position: at, total }));
    setVersion((v) => v + 1);
  };

  const onRemove = (component: Component) => {
    const kind = kindOf(intl, component);
    const result = removeBlock(editor, component);
    if (!result.ok) {
      announce(intl.formatMessage({ id: 'templates.kb_locked' }, { kind }));
      return;
    }
    if (result.next) focusAfter.current = idOf(result.next);
    announce(intl.formatMessage({ id: 'templates.kb_removed' }, { kind }));
    setVersion((v) => v + 1);
  };

  const focusProperties = () => {
    document
      .querySelector<HTMLElement>('[data-kb-properties] input, [data-kb-properties] select')
      ?.focus();
  };

  const onTreeKey = (event: React.KeyboardEvent<HTMLDivElement>) => {
    const row = (event.target as HTMLElement).closest<HTMLElement>('[data-id]');
    if (!row) return;
    const index = items.findIndex((item) => item.id === row.dataset.id);
    const item = items[index];
    if (!item) return;
    const goto = (target?: OutlineItem) => {
      if (!target) return;
      event.preventDefault();
      focusAfter.current = target.id;
      selectItem(target);
      setVersion((v) => v + 1);
    };

    if (event.altKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
      event.preventDefault();
      onMove(item.component, event.key === 'ArrowUp' ? -1 : 1);
      return;
    }
    switch (event.key) {
      case 'ArrowDown':
        goto(items[index + 1]);
        break;
      case 'ArrowUp':
        goto(items[index - 1]);
        break;
      case 'Home':
        goto(items[0]);
        break;
      case 'End':
        goto(items[items.length - 1]);
        break;
      case 'ArrowRight':
        if (item.hasChildren && collapsed.has(item.id)) {
          event.preventDefault();
          setCollapsed((previous) => {
            const next = new Set(previous);
            next.delete(item.id);
            return next;
          });
        } else if (item.hasChildren) {
          goto(items[index + 1]);
        }
        break;
      case 'ArrowLeft':
        if (item.hasChildren && !collapsed.has(item.id)) {
          event.preventDefault();
          setCollapsed((previous) => new Set(previous).add(item.id));
        } else {
          goto(
            [...items.slice(0, index)].reverse().find((candidate) => candidate.level < item.level),
          );
        }
        break;
      case 'Enter':
      case ' ':
        event.preventDefault();
        if (item.type === 'mj-text') beginEdit(item.component);
        else if (item.type === 'mj-image') chooseImage(item.component);
        else focusProperties();
        break;
      case 'Delete':
      case 'Backspace':
        event.preventDefault();
        onRemove(item.component);
        break;
      default:
    }
  };

  const onToolbarKey = (event: React.KeyboardEvent<HTMLDivElement>) => {
    const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
    if (!keys.includes(event.key)) return;
    const buttons = Array.from(
      toolbar.current?.querySelectorAll<HTMLButtonElement>('button:not([disabled])') ?? [],
    );
    const current = buttons.indexOf(document.activeElement as HTMLButtonElement);
    if (current < 0) return;
    event.preventDefault();
    const next =
      event.key === 'Home'
        ? 0
        : event.key === 'End'
        ? buttons.length - 1
        : (current + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
    buttons[next]?.focus();
  };

  const kindName = selected ? kindOf(intl, selected) : '';
  const canMove = !!selected && selected.get('draggable') !== false && !!selected.parent();
  const isText = selected?.get('type') === 'mj-text';
  const isImage = selected?.get('type') === 'mj-image';
  const removable = !!selected && selected.get('removable') !== false;

  // The toolbar is one tab stop: the first enabled button gets tabindex 0, the others -1.
  const toolbarButtons = useMemo(
    () => [
      {
        key: 'up',
        label: 'templates.kb_move_up',
        disabled: !canMove,
        run: () => selected && onMove(selected, -1),
      },
      {
        key: 'down',
        label: 'templates.kb_move_down',
        disabled: !canMove,
        run: () => selected && onMove(selected, 1),
      },
      {
        key: 'remove',
        label: 'templates.kb_remove',
        disabled: !removable,
        run: () => selected && onRemove(selected),
      },
      {
        key: 'edit',
        label: 'templates.kb_edit_text',
        disabled: !isText,
        run: () => selected && beginEdit(selected),
      },
      {
        key: 'image',
        label: 'templates.kb_choose_image',
        disabled: !isImage,
        run: () => selected && chooseImage(selected),
      },
      {
        key: 'undo',
        label: 'templates.kb_undo',
        disabled: !editor.UndoManager.hasUndo(),
        run: () => {
          editor.UndoManager.undo();
          announce(intl.formatMessage({ id: 'templates.kb_undone' }));
        },
      },
      {
        key: 'redo',
        label: 'templates.kb_redo',
        disabled: !editor.UndoManager.hasRedo(),
        run: () => {
          editor.UndoManager.redo();
          announce(intl.formatMessage({ id: 'templates.kb_redone' }));
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [
      selected,
      canMove,
      removable,
      isText,
      isImage,
      status,
      editor.UndoManager.hasUndo(),
      editor.UndoManager.hasRedo(),
    ],
  );
  const firstEnabled = toolbarButtons.findIndex((button) => !button.disabled);

  return (
    <section
      className="email-template-editor__kb"
      aria-labelledby="email-editor-kb-title"
      data-testid="keyboard-panel"
    >
      <h4 id="email-editor-kb-title">{intl.formatMessage({ id: 'templates.kb_title' })}</h4>
      <p className="email-template-editor__hint" id="email-editor-kb-intro">
        {intl.formatMessage({ id: 'templates.kb_intro' })}
      </p>
      <details className="email-template-editor__keys">
        <summary>{intl.formatMessage({ id: 'templates.kb_keys_title' })}</summary>
        <ul>
          {['tree', 'enter', 'move', 'text', 'toolbar'].map((key) => (
            <li key={key}>{intl.formatMessage({ id: `templates.kb_keys_${key}` })}</li>
          ))}
        </ul>
      </details>
      <div
        aria-live="polite"
        role="status"
        aria-atomic="true"
        data-testid="kb-status"
        className="email-template-editor__announce"
      >
        {status}
      </div>
      <div className="email-template-editor__kb-grid">
        <div className="email-template-editor__kb-main">
          <div
            role="group"
            aria-label={intl.formatMessage({ id: 'templates.kb_add_group' })}
            className="email-template-editor__kb-add"
          >
            <label htmlFor="email-editor-kb-block">
              {intl.formatMessage({ id: 'templates.kb_add_label' })}
            </label>
            <select
              id="email-editor-kb-block"
              value={blockId}
              onChange={(e) => setBlockId(e.target.value)}
            >
              {BLOCKS.map((id) => (
                <option key={id} value={id}>
                  {intl.formatMessage({ id: `templates.kb_block_${id}` })}
                </option>
              ))}
            </select>
            <button type="button" className="email-template-editor__kb-button" onClick={onAdd}>
              {intl.formatMessage({ id: 'templates.kb_add' })}
            </button>
          </div>
          <div
            ref={toolbar}
            role="toolbar"
            aria-label={intl.formatMessage({ id: 'templates.kb_actions' })}
            aria-orientation="horizontal"
            className="email-template-editor__kb-toolbar"
            onKeyDown={onToolbarKey}
          >
            {toolbarButtons.map((button, i) => (
              <button
                key={button.key}
                type="button"
                className="email-template-editor__kb-button"
                disabled={button.disabled}
                tabIndex={i === firstEnabled ? 0 : -1}
                onClick={button.run}
              >
                {intl.formatMessage({ id: button.label })}
              </button>
            ))}
          </div>
          <div
            ref={tree}
            role="tree"
            aria-label={intl.formatMessage({ id: 'templates.kb_tree' })}
            aria-describedby="email-editor-kb-intro"
            className="email-template-editor__kb-tree"
            onKeyDown={onTreeKey}
          >
            {items.map((item) => {
              const { kind, excerpt } = describe(item.component, kindOf(intl, item.component));
              const isSelected = item.component === selected;
              return (
                <div
                  key={item.id}
                  role="treeitem"
                  data-id={item.id}
                  aria-level={item.level}
                  aria-posinset={item.position}
                  aria-setsize={item.siblings}
                  aria-selected={isSelected}
                  aria-expanded={item.hasChildren ? !collapsed.has(item.id) : undefined}
                  tabIndex={item.id === activeId ? 0 : -1}
                  className={`email-template-editor__kb-item${isSelected ? ' is-selected' : ''}`}
                  style={{ paddingInlineStart: `${(item.level - 1) * 20 + 8}px` }}
                  onClick={() => selectItem(item)}
                  onFocus={() => selectItem(item)}
                >
                  <span className="email-template-editor__kb-kind">{kind}</span>
                  {excerpt && <span className="email-template-editor__kb-excerpt"> {excerpt}</span>}
                  {item.locked && item.type !== 'mj-body' && (
                    <span className="email-template-editor__kb-excerpt">
                      {' '}
                      ({intl.formatMessage({ id: 'templates.kb_locked_tag' })})
                    </span>
                  )}
                </div>
              );
            })}
          </div>
        </div>
        <PropertiesForm
          editor={editor}
          component={selected}
          kind={kindName}
          onChanged={(label) =>
            announce(intl.formatMessage({ id: 'templates.kb_prop_set' }, { label }))
          }
        />
      </div>
    </section>
  );
};

export default KeyboardPanel;
