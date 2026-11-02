import { describe, it, expect } from "vitest";
import { faqContent } from '../../web/src/lib/faq';

describe('faqContent', () => {



  it('should return undefined for an unsupported locale', () => {
    const content = faqContent('fr');
    expect(content).toBeUndefined();
  });

  it('should return undefined for an empty locale string', () => {
    const content = faqContent('');
    expect(content).toBeUndefined();
  });

  it('should return undefined for a null locale', () => {
    const content = faqContent(null as any);
    expect(content).toBeUndefined();
  });

  it('should return undefined for an undefined locale', () => {
    const content = faqContent(undefined as any);
    expect(content).toBeUndefined();
  });

  it('should return undefined for a non-string locale', () => {
    const content = faqContent(123 as any);
    expect(content).toBeUndefined();
  });
});
