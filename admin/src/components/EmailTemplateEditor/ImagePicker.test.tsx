/**
 * @jest-environment jsdom
 */
import { afterEach, beforeAll, describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';

jest.mock('umi', () => ({
  FormattedMessage: ({ id }: { id: string }) => id,
  useIntl: () => ({ formatMessage: (d: { id: string }) => d.id }),
  request: jest.fn(),
}));
jest.mock('@/services/ulams/templates', () => ({ uploadEmailImage: jest.fn() }));

import ImagePicker, { MAX_IMAGE_BYTES, rejectReason } from './ImagePicker';

const stored = { url: 'https://cdn.test/email-images/a.png', name: 'email-images/a.png' };
const file = (name: string, type: string, size = 100) => {
  const f = new File(['x'], name, { type });
  Object.defineProperty(f, 'size', { value: size });
  return f;
};

const setup = (upload: jest.Mock<(f: File) => Promise<unknown>>) => {
  const onDone = jest.fn();
  render(<ImagePicker open onDone={onDone} upload={upload} />);
  const input = () => screen.getByLabelText('templates.image_file') as HTMLInputElement;
  const choose = async (f: File) => {
    await act(async () => {
      fireEvent.change(input(), { target: { files: [f] } });
    });
  };
  return { onDone, choose };
};

describe('ImagePicker', () => {
  beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: (query: string) => ({
        matches: false,
        media: query,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
      }),
    });
  });
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('refuses SVG, scripts and other types before anything is sent', async () => {
    const upload = jest.fn<(f: File) => Promise<unknown>>();
    const { choose } = setup(upload);
    for (const f of [
      file('logo.svg', 'image/svg+xml'),
      file('logo.png.svg', 'image/png'),
      file('x.html', 'text/html'),
      file('shell.php', 'application/x-php'),
      file('logo.png', 'image/svg+xml'),
    ]) {
      await choose(f);
      expect(await screen.findByRole('alert')).toHaveTextContent('templates.image_wrong_type');
    }
    expect(upload).not.toHaveBeenCalled();
  });

  it('refuses an image over the size limit before it is sent', async () => {
    const upload = jest.fn<(f: File) => Promise<unknown>>();
    const { choose } = setup(upload);
    await choose(file('big.png', 'image/png', MAX_IMAGE_BYTES + 1));
    expect(await screen.findByRole('alert')).toHaveTextContent('templates.image_too_large');
    expect(upload).not.toHaveBeenCalled();
    expect(
      rejectReason({ name: 'ok.webp', type: 'image/webp', size: MAX_IMAGE_BYTES }),
    ).toBeUndefined();
  });

  it('needs alternative text before the image can be inserted', async () => {
    const upload = jest.fn<(f: File) => Promise<unknown>>().mockResolvedValue({ data: stored });
    const { choose, onDone } = setup(upload);
    await choose(file('logo.png', 'image/png'));
    const insert = await screen.findByRole('button', { name: 'templates.image_insert' });
    expect(upload).toHaveBeenCalledTimes(1);
    expect(insert).toBeDisabled();
    expect(screen.getByLabelText(/templates.image_alt/)).toBeRequired();

    fireEvent.change(screen.getByLabelText(/templates.image_alt/), {
      target: { value: '  Academy logo ' },
    });
    expect(insert).toBeEnabled();
    fireEvent.click(insert);
    expect(onDone).toHaveBeenCalledWith({ url: stored.url, alt: 'Academy logo' });
  });

  it('accepts an explicitly decorative image with an empty alt', async () => {
    const upload = jest.fn<(f: File) => Promise<unknown>>().mockResolvedValue({ data: stored });
    const { choose, onDone } = setup(upload);
    await choose(file('line.gif', 'image/gif'));
    const insert = await screen.findByRole('button', { name: 'templates.image_insert' });
    fireEvent.click(screen.getByLabelText('templates.image_decorative'));
    expect(insert).toBeEnabled();
    fireEvent.click(insert);
    expect(onDone).toHaveBeenCalledWith({ url: stored.url, alt: '' });
  });

  it('shows the message of the server when the upload is refused and inserts nothing', async () => {
    const upload = jest.fn<(f: File) => Promise<unknown>>().mockRejectedValue({
      response: { data: { errors: { image: ['The file is not a valid image.'] } } },
    });
    const { choose, onDone } = setup(upload);
    await choose(file('fake.png', 'image/png'));
    expect(await screen.findByRole('alert')).toHaveTextContent('The file is not a valid image.');
    expect(screen.queryByRole('button', { name: 'templates.image_insert' })).toBeDisabled();
    expect(onDone).not.toHaveBeenCalled();
  });

  it('resolves with nothing when cancelled', async () => {
    const { onDone } = setup(jest.fn<(f: File) => Promise<unknown>>());
    fireEvent.click(screen.getByRole('button', { name: 'templates.image_cancel' }));
    await waitFor(() => expect(onDone).toHaveBeenCalledWith(undefined));
  });
});
