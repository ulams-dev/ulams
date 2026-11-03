import { describe, it, expect } from "vitest";
import { diffFields } from '../../src/registry/diff';

describe('diffFields', () => {
  it('should return no changes for identical objects', () => {
    const current = { a: 1, b: 2 };
    const desired = { a: 1, b: 2 };
    expect(diffFields(current, desired)).toEqual([]);
  });

  it('should detect added fields', () => {
    const current = { a: 1 };
    const desired = { a: 1, b: 2 };
    expect(diffFields(current, desired)).toEqual([{ op: 'add', path: '/b', to: 2 }]);
  });


  it('should detect replaced fields', () => {
    const current = { a: 1, b: 2 };
    const desired = { a: 1, b: 3 };
    expect(diffFields(current, desired)).toEqual([{ op: 'replace', path: '/b', from: 2, to: 3 }]);
  });

  it('should handle null values correctly', () => {
    const current = { a: null };
    const desired = { a: 1 };
    expect(diffFields(current, desired)).toEqual([{ op: 'replace', path: '/a', from: null, to: 1 }]);
  });



  it('should handle null current object', () => {
    const current = null;
    const desired = { a: 1 };
    expect(diffFields(current, desired)).toEqual([{ op: 'add', path: '/a', to: 1 }]);
  });
});
