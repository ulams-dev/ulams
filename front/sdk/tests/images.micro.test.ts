import { describe, it, expect } from "vitest";
import { IMAGE_PRESETS, allowedCloudflareOptions, resolveImageService } from '../src/images';

describe('IMAGE_PRESETS', () => {

  it('should handle unknown preset gracefully', () => {
    const unknownPreset = IMAGE_PRESETS['unknown' as keyof typeof IMAGE_PRESETS];
    expect(unknownPreset).toBeUndefined();
  });
});


describe('resolveImageService', () => {
  it('should return fallback for unknown service', () => {
    const service = resolveImageService('unknown', 'cloudflare');
    expect(service).toBe('cloudflare');
  });

  it('should return "noop" for unsupported service', () => {
    const service = resolveImageService('cloudflare', 'cloudflare', ['astro']);
    expect(service).toBe('noop');
  });

  it('should return the known service if supported', () => {
    const service = resolveImageService('cloudflare', 'cloudflare', ['cloudflare']);
    expect(service).toBe('cloudflare');
  });

  it('should handle empty value', () => {
    const service = resolveImageService('', 'cloudflare');
    expect(service).toBe('cloudflare');
  });

  it('should handle null value', () => {
    const service = resolveImageService(null, 'cloudflare');
    expect(service).toBe('cloudflare');
  });

  it('should handle undefined value', () => {
    const service = resolveImageService(undefined, 'cloudflare');
    expect(service).toBe('cloudflare');
  });
});
