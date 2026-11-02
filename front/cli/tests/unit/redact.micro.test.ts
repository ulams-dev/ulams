import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { redact, REDACTED } from '../../src/config/redact';

describe('redact', () => {
  it('should redact JWT tokens', () => {
    const input = { token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9' };
    const expected = { token: REDACTED };
    expect(redact(input)).toEqual(expected);
  });

  it('should redact ulams_pat_ strings', () => {
    const input = { pat: 'ulams_pat_abc123' };
    const expected = { pat: REDACTED };
    expect(redact(input)).toEqual(expected);
  });

  it('should redact Bearer tokens', () => {
    const input = { auth: 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9' };
    const expected = { auth: 'Bearer «redacted»' };
    expect(redact(input)).toEqual(expected);
  });

  it('should redact nested JWT tokens', () => {
    const input = { user: { token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9' } };
    const expected = { user: { token: REDACTED } };
    expect(redact(input)).toEqual(expected);
  });

  it('should redact nested ulams_pat_ strings', () => {
    const input = { user: { pat: 'ulams_pat_abc123' } };
    const expected = { user: { pat: REDACTED } };
    expect(redact(input)).toEqual(expected);
  });

  it('should redact nested Bearer tokens', () => {
    const input = { user: { auth: 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9' } };
    const expected = { user: { auth: 'Bearer «redacted»' } };
    expect(redact(input)).toEqual(expected);
  });

  it('should redact extra secrets', () => {
    const input = { secret: 'mysecret', extra: 'mysecret' };
    const expected = { secret: REDACTED, extra: REDACTED };
    expect(redact(input, ['mysecret'])).toEqual(expected);
  });

  it('should redact nested extra secrets', () => {
    const input = { user: { secret: 'mysecret', extra: 'mysecret' } };
    const expected = { user: { secret: REDACTED, extra: REDACTED } };
    expect(redact(input, ['mysecret'])).toEqual(expected);
  });
});
