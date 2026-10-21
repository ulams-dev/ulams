/** Public setting (Settings, group global) that turns the visual mode of the e-mail editor off. */
export const VISUAL_EDITOR_SETTING = 'templates.email.visual_editor';

type SettingRow = { key?: string; data?: unknown };

/**
 * The visual mode is on unless an operator switched it off (plan Q7). A missing setting means on,
 * and so does anything that is not an explicit "false".
 */
export const isVisualEditorEnabled = (config: SettingRow[] | undefined): boolean => {
  const row = config?.find((item) => item.key === VISUAL_EDITOR_SETTING);
  if (!row) return true;
  const { data } = row;
  if (data === false || data === 0) return false;
  if (typeof data === 'string')
    return !['false', '0', 'off', 'no'].includes(data.trim().toLowerCase());
  return true;
};
