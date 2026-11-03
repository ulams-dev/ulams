import { describe, it, expect } from "vitest";
import { labelGeneratedCourses, showAiAssistedLabel } from '../../web/src/lib/ai-label';

describe('labelGeneratedCourses', () => {
  it('returns true when config is null', () => {
    expect(labelGeneratedCourses(null)).toBe(true);
  });

  it('returns true when config is undefined', () => {
    expect(labelGeneratedCourses(undefined)).toBe(true);
  });

  it('returns true when ai.label_generated_courses is not present', () => {
    expect(labelGeneratedCourses({})).toBe(true);
  });

  it('returns true when ai.label_generated_courses is true', () => {
    expect(labelGeneratedCourses({ ai: { label_generated_courses: true } })).toBe(true);
  });

  it('returns false when ai.label_generated_courses is false', () => {
    expect(labelGeneratedCourses({ ai: { label_generated_courses: false } })).toBe(false);
  });

  it('returns false when ai.label_generated_courses is 0', () => {
    expect(labelGeneratedCourses({ ai: { label_generated_courses: 0 } })).toBe(false);
  });

  it('returns false when ai.label_generated_courses is "off"', () => {
    expect(labelGeneratedCourses({ ai: { label_generated_courses: 'off' } })).toBe(false);
  });

  it('returns true when ai.label_generated_courses is "on"', () => {
    expect(labelGeneratedCourses({ ai: { label_generated_courses: 'on' } })).toBe(true);
  });
});

describe('showAiAssistedLabel', () => {
  it('returns false when topic.ai_assisted is false', () => {
    expect(showAiAssistedLabel({ ai_assisted: false }, {})).toBe(false);
  });

  it('returns false when topic.ai_assisted is true but labelGeneratedCourses returns false', () => {
    expect(showAiAssistedLabel({ ai_assisted: true }, { ai: { label_generated_courses: false } })).toBe(false);
  });

  it('returns true when topic.ai_assisted is true and labelGeneratedCourses returns true', () => {
    expect(showAiAssistedLabel({ ai_assisted: true }, {})).toBe(true);
  });
});
