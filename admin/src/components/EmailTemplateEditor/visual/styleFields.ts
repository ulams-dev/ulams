/**
 * The style properties the keyboard form offers for a block, in display order. They are the MJML
 * attributes the blocks of the editor allow (`stylable` of each component of grapesjs-mjml); the form
 * writes them the same way the style manager of GrapesJS does, so the exported MJML is identical.
 * Labels are `templates.kb_style_<property>` in the admin locales (the plugin has English names only).
 */
export type StyleField = {
  property: string;
  kind: 'text' | 'select' | 'color';
  options?: string[];
};

const ALIGN = ['left', 'center', 'right', 'justify'];

export const STYLE_FIELDS: StyleField[] = [
  { property: 'width', kind: 'text' },
  { property: 'height', kind: 'text' },
  { property: 'font-family', kind: 'text' },
  { property: 'font-size', kind: 'text' },
  {
    property: 'font-weight',
    kind: 'select',
    options: ['normal', 'bold', '300', '400', '500', '600', '700'],
  },
  { property: 'font-style', kind: 'select', options: ['normal', 'italic'] },
  { property: 'line-height', kind: 'text' },
  { property: 'letter-spacing', kind: 'text' },
  { property: 'color', kind: 'color' },
  { property: 'text-decoration', kind: 'select', options: ['none', 'underline', 'line-through'] },
  {
    property: 'text-transform',
    kind: 'select',
    options: ['none', 'uppercase', 'lowercase', 'capitalize'],
  },
  { property: 'align', kind: 'select', options: ALIGN },
  { property: 'vertical-align', kind: 'select', options: ['top', 'middle', 'bottom'] },
  { property: 'background-color', kind: 'color' },
  { property: 'container-background-color', kind: 'color' },
  { property: 'padding', kind: 'text' },
  { property: 'padding-top', kind: 'text' },
  { property: 'padding-right', kind: 'text' },
  { property: 'padding-bottom', kind: 'text' },
  { property: 'padding-left', kind: 'text' },
  { property: 'border-radius', kind: 'text' },
  { property: 'border', kind: 'text' },
];

/** Shorthands whose sides are offered instead when both are allowed (`padding`). */
const SIDES_PREFERRED = ['padding'];

/** Fields of a block, given the `stylable` list of its component. */
export const fieldsFor = (stylable: unknown): StyleField[] => {
  if (!Array.isArray(stylable)) return [];
  const allowed = new Set(stylable.map(String));
  return STYLE_FIELDS.filter((field) => {
    if (!allowed.has(field.property)) return false;
    if (SIDES_PREFERRED.includes(field.property) && allowed.has(`${field.property}-top`)) {
      return false;
    }
    return true;
  });
};
