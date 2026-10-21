import { describe, expect, it } from '@jest/globals';
import { isVisualEditorEnabled, VISUAL_EDITOR_SETTING } from './featureSwitch';

describe('isVisualEditorEnabled', () => {
  it('is on by default and for anything but an explicit false', () => {
    expect(isVisualEditorEnabled(undefined)).toBe(true);
    expect(isVisualEditorEnabled([])).toBe(true);
    expect(isVisualEditorEnabled([{ key: 'other', data: false }])).toBe(true);
    expect(isVisualEditorEnabled([{ key: VISUAL_EDITOR_SETTING, data: true }])).toBe(true);
    expect(isVisualEditorEnabled([{ key: VISUAL_EDITOR_SETTING, data: 'true' }])).toBe(true);
  });

  it('is off when an operator sets the setting to false', () => {
    expect(isVisualEditorEnabled([{ key: VISUAL_EDITOR_SETTING, data: false }])).toBe(false);
    expect(isVisualEditorEnabled([{ key: VISUAL_EDITOR_SETTING, data: 'false' }])).toBe(false);
    expect(isVisualEditorEnabled([{ key: VISUAL_EDITOR_SETTING, data: '0' }])).toBe(false);
  });
});
