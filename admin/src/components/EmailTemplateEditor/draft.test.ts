import { describe, expect, it } from '@jest/globals';
import { insertAtSelection, requestErrorMessage } from './draft';

describe('insertAtSelection', () => {
  it('inserts at the caret', () => {
    expect(insertAtSelection('Hello !', 6, 6, '@UserName')).toEqual({
      value: 'Hello @UserName!',
      caret: 15,
    });
  });

  it('replaces the selection', () => {
    expect(insertAtSelection('Hello world', 6, 11, '@UserName').value).toBe('Hello @UserName');
  });

  it('appends when there is no selection info and clamps out of range values', () => {
    expect(insertAtSelection('abc', null, null, 'X').value).toBe('abcX');
    expect(insertAtSelection('abc', 99, 120, 'X').value).toBe('abcX');
  });
});

describe('requestErrorMessage', () => {
  it('prefers the first validation error', () => {
    const error = { response: { data: { message: 'Invalid', errors: { content: ['Bad MJML'] } } } };
    expect(requestErrorMessage(error, 'fallback')).toBe('Bad MJML');
  });

  it('falls back to the message and then to the fallback', () => {
    expect(requestErrorMessage({ response: { data: { message: 'Busy' } } }, 'fallback')).toBe(
      'Busy',
    );
    expect(requestErrorMessage({}, 'fallback')).toBe('fallback');
  });
});
