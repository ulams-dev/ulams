import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { h5pUsesSession, h5pSearch } from '../web/src/lib/h5p-proxy';

describe('h5p-proxy', () => {
  describe('h5pUsesSession', () => {
    it('should return true for embed/play/:id', () => {
      expect(h5pUsesSession('GET', 'embed/play/123')).toBe(true);
    });

    it('should return true for contents/:id/play', () => {
      expect(h5pUsesSession('GET', 'contents/123/play')).toBe(true);
    });

    it('should return true for contentUserData/:id/:segment/:segment', () => {
      expect(h5pUsesSession('GET', 'contentUserData/123/segment1/segment2')).toBe(true);
      expect(h5pUsesSession('POST', 'contentUserData/123/segment1/segment2')).toBe(true);
    });

    it('should return true for finishedData', () => {
      expect(h5pUsesSession('POST', 'finishedData')).toBe(true);
    });

    it('should return false for other paths', () => {
      expect(h5pUsesSession('GET', 'libraries')).toBe(false);
      expect(h5pUsesSession('POST', 'contents')).toBe(false);
      expect(h5pUsesSession('GET', 'editor')).toBe(false);
      expect(h5pUsesSession('GET', 'admin')).toBe(false);
    });

    it('should return false for invalid paths', () => {
      expect(h5pUsesSession('GET', 'embed/play/')).toBe(false);
      expect(h5pUsesSession('GET', 'contents/123/')).toBe(false);
      expect(h5pUsesSession('GET', 'contentUserData/123/segment1/')).toBe(false);
      expect(h5pUsesSession('GET', 'contentUserData/123//segment2')).toBe(false);
    });
  });

  describe('h5pSearch', () => {
    it('should remove _token from query string', () => {
      expect(h5pSearch('?_token=abc&param=value')).toBe('?param=value');
    });

    it('should return empty string if _token is the only parameter', () => {
      expect(h5pSearch('?_token=abc')).toBe('');
    });

    it('should return original query string if _token is not present', () => {
      expect(h5pSearch('?param=value')).toBe('?param=value');
    });

    it('should return empty string if query string is empty', () => {
      expect(h5pSearch('')).toBe('');
    });
  });
});
