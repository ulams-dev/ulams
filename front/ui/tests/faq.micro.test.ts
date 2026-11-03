import { describe, it, expect } from "vitest";
import { faqContent } from '../../web/src/lib/faq';

describe('faqContent', () => {



  it('should return undefined for an unsupported locale', () => {
    const content = faqContent('fr' as never);
    expect(content).toBeUndefined();
  });

  it('should return undefined for an empty locale string', () => {
    const content = faqContent('' as never);
    expect(content).toBeUndefined();
  });

  it('should return undefined for a null locale', () => {
    const content = faqContent(null as never);
    expect(content).toBeUndefined();
  });

  it('should return undefined for an undefined locale', () => {
    const content = faqContent(undefined as never);
    expect(content).toBeUndefined();
  });

  it('should return undefined for a non-string locale', () => {
    const content = faqContent(123 as never);
    expect(content).toBeUndefined();
  });
});
