/**
 * @jest-environment jsdom
 */
import { expect } from '@jest/globals';
import { fireEvent, render } from '@testing-library/react';
import ResponsiveImage from './index';

const STORAGE = 'https://storage.example.org';
const setWindow = (settings: Record<string, string | undefined>) => {
  for (const [key, value] of Object.entries(settings)) {
    (window as unknown as Record<string, unknown>)[key] = value;
  }
};

beforeEach(() =>
  setWindow({
    REACT_APP_API_URL: 'https://api.example.test',
    REACT_APP_IMAGE_SERVICE: undefined,
    REACT_APP_IMAGE_SOURCES: STORAGE,
  }),
);

const img = (container: HTMLElement) => container.querySelector('img') as HTMLImageElement;

describe('ResponsiveImage', () => {
  it("php mode renders today's resizer URL", () => {
    const { container } = render(
      <ResponsiveImage
        path="courses/1.png"
        url={`${STORAGE}/courses/1.png`}
        size={240}
        alt="Course"
      />,
    );
    expect(img(container).getAttribute('src')).toBe(
      'https://api.example.test/api/images/img?path=courses/1.png&w=240',
    );
    expect(img(container).getAttribute('alt')).toBe('Course');
  });

  it('cloudflare mode renders a distinct-width srcset and falls back to the original on error', () => {
    setWindow({ REACT_APP_IMAGE_SERVICE: 'cloudflare' });
    const { container } = render(
      <ResponsiveImage
        path="courses/1.png"
        url={`${STORAGE}/courses/1.png`}
        size={240}
        preset="card"
        alt="Course"
      />,
    );
    const first = img(container);
    expect(first.getAttribute('src')).toContain('/cdn-cgi/image/width=240,');
    expect(first.getAttribute('srcset')).toMatch(/width=240,.* 240w, .*width=480,.* 480w/);

    fireEvent.error(first);
    const after = img(container);
    expect(after.getAttribute('src')).toBe(`${STORAGE}/courses/1.png`);
    expect(after.getAttribute('srcset')).toBeNull();
  });

  it('noop mode renders the original without srcset', () => {
    setWindow({ REACT_APP_IMAGE_SERVICE: 'noop' });
    const { container } = render(
      <ResponsiveImage
        path="courses/1.png"
        url={`${STORAGE}/courses/1.png`}
        size={240}
        preset="card"
      />,
    );
    expect(img(container).getAttribute('src')).toBe(`${STORAGE}/courses/1.png`);
    expect(img(container).getAttribute('srcset')).toBeNull();
  });
});
