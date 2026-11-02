import { describe, it, expect } from "vitest";
import { ulid } from '../../src/http/ulid';

describe('ulid', () => {
  it('should generate a 26-character string', () => {
    expect(ulid()).toHaveLength(26);
  });

  it('should use the correct alphabet', () => {
    const id = ulid();
    const ALPHABET = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";
    for (const char of id) {
      expect(ALPHABET).toContain(char);
    }
  });

  it('should be time-sortable', () => {
    const first = ulid();
    const second = ulid();
    expect(first < second).toBe(true);
  });

  it('should generate unique IDs across calls', () => {
    const ids = new Set<string>();
    for (let i = 0; i < 100; i++) {
      ids.add(ulid());
    }
    expect(ids.size).toBe(100);
  });


  it('should generate random parts correctly', () => {
    const id = ulid();
    const randomPart = id.slice(10);
    const ALPHABET = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";
    for (const char of randomPart) {
      expect(ALPHABET).toContain(char);
    }
  });

  it('should handle zero time input correctly', () => {
    const id = ulid(0);
    const timePart = id.slice(0, 10);
    expect(timePart).toBe('0000000000');
  });
});
