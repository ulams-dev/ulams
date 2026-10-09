import { request } from 'umi';
import {
  addAdaptVersion,
  buildAdaptSource,
  createAdaptSource,
  isAdaptDisabled,
  isBuilding,
} from './adapt';

jest.mock('umi', () => ({ request: jest.fn().mockResolvedValue({ success: true, data: {} }) }));

describe('adapt service', () => {
  beforeEach(() => (request as jest.Mock).mockClear());

  it('parses pasted JSON before sending it', async () => {
    await createAdaptSource('{"course":{"title":"T"}}', 'My course');
    expect(request).toHaveBeenCalledWith('/api/admin/adapt', {
      method: 'POST',
      data: { source: { course: { title: 'T' } }, title: 'My course' },
    });
  });

  it('rejects text that is not JSON', () => {
    expect(() => createAdaptSource('{nope')).toThrow();
  });

  it('sends the change note with a new version', async () => {
    await addAdaptVersion(4, { course: {} }, 'wording');
    expect(request).toHaveBeenCalledWith('/api/admin/adapt/4/versions', {
      method: 'POST',
      data: { source: { course: {} }, change_note: 'wording' },
    });
  });

  it('posts a build and recognises a running one', async () => {
    await buildAdaptSource(9);
    expect(request).toHaveBeenCalledWith('/api/admin/adapt/9/build', { method: 'POST' });
    expect(isBuilding({ status: 'building' })).toBe(true);
    expect(isBuilding({ status: 'built' })).toBe(false);
    expect(isBuilding(undefined)).toBe(false);
  });

  it('treats a 404 as the feature flag being off', () => {
    expect(isAdaptDisabled({ response: { status: 404 } })).toBe(true);
    expect(isAdaptDisabled({ response: { status: 403 } })).toBe(false);
    expect(isAdaptDisabled(undefined)).toBe(false);
  });
});
