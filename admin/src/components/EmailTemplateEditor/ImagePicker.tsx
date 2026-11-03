import { uploadEmailImage, type EmailImage } from '@/services/ulams/templates';
import { Alert, Checkbox, Input, Modal, Spin } from 'antd';
import React, { useEffect, useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';
import { requestErrorMessage } from './draft';

/** Raster formats the server accepts (SVG is refused: it can carry scripts, most mail clients ignore it). */
export const ACCEPTED_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
export const ACCEPTED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
/** Same default as the server (`ulams_templates_email.images.max_kb`), which stays the authority. */
export const MAX_IMAGE_BYTES = 2048 * 1024;

/** Reason a file is refused before it is sent, or undefined when it may be uploaded. */
export const rejectReason = (
  file: Pick<File, 'name' | 'type' | 'size'>,
): 'type' | 'size' | undefined => {
  const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
  if (
    !ACCEPTED_EXTENSIONS.includes(extension) ||
    (file.type && !ACCEPTED_TYPES.includes(file.type))
  ) {
    return 'type';
  }
  return file.size > MAX_IMAGE_BYTES ? 'size' : undefined;
};

export type ImagePickerProps = {
  open: boolean;
  /** Called with the image and its alt text (empty for a decorative image), or undefined on cancel. */
  onDone: (image: { url: string; alt: string } | undefined) => void;
  /** Replaceable for tests. */
  upload?: (file: File) => Promise<unknown>;
};

const storedImage = (response: unknown): EmailImage | undefined => {
  const body = response as { data?: EmailImage & { data?: EmailImage } };
  return body?.data?.url ? body.data : body?.data?.data;
};

/**
 * Upload dialog of the visual e-mail editor: the image goes to the storage of the current tenant,
 * then needs alt text (or the explicit choice "decorative") before it is inserted. It replaces the
 * built-in asset manager of GrapesJS, which has neither validation nor alt text.
 */
const ImagePicker: React.FC<ImagePickerProps> = ({
  open,
  onDone,
  upload = uploadEmailImage as unknown as (file: File) => Promise<unknown>,
}) => {
  const intl = useIntl();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | undefined>();
  const [image, setImage] = useState<EmailImage | undefined>();
  const [alt, setAlt] = useState('');
  const [decorative, setDecorative] = useState(false);

  useEffect(() => {
    if (open) {
      setBusy(false);
      setError(undefined);
      setImage(undefined);
      setAlt('');
      setDecorative(false);
    }
  }, [open]);

  const onFile = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    setImage(undefined);
    const reason = rejectReason(file);
    if (reason) {
      setError(
        intl.formatMessage({
          id: reason === 'type' ? 'templates.image_wrong_type' : 'templates.image_too_large',
        }),
      );
      return;
    }
    setError(undefined);
    setBusy(true);
    try {
      const stored = storedImage(await upload(file));
      if (!stored) throw new Error(intl.formatMessage({ id: 'templates.image_failed' }));
      setImage(stored);
    } catch (e) {
      setError(requestErrorMessage(e, intl.formatMessage({ id: 'templates.image_failed' })));
    } finally {
      setBusy(false);
    }
  };

  const altOk = decorative || alt.trim().length > 0;

  return (
    <Modal
      open={open}
      title={intl.formatMessage({ id: 'templates.image_title' })}
      okText={intl.formatMessage({ id: 'templates.image_insert' })}
      cancelText={intl.formatMessage({ id: 'templates.image_cancel' })}
      okButtonProps={{ disabled: !image || !altOk || busy }}
      onOk={() => image && altOk && onDone({ url: image.url, alt: decorative ? '' : alt.trim() })}
      onCancel={() => onDone(undefined)}
      destroyOnClose
    >
      <p>
        <FormattedMessage id="templates.image_hint" />
      </p>
      <label htmlFor="email-image-file">
        <FormattedMessage id="templates.image_file" />
      </label>
      <br />
      <input
        id="email-image-file"
        type="file"
        accept={ACCEPTED_TYPES.join(',')}
        onChange={onFile}
        disabled={busy}
      />
      <div aria-live="polite">
        {busy && (
          <span>
            <Spin size="small" /> <FormattedMessage id="templates.image_uploading" />
          </span>
        )}
        {error && <Alert type="error" showIcon role="alert" message={error} />}
      </div>
      {image && (
        <div>
          <img
            src={image.url}
            alt=""
            style={{ maxWidth: '100%', maxHeight: 160, display: 'block', margin: '12px 0' }}
          />
          <label htmlFor="email-image-alt">
            <FormattedMessage id="templates.image_alt" /> *
          </label>
          <Input
            id="email-image-alt"
            value={alt}
            disabled={decorative}
            required
            aria-required="true"
            aria-describedby="email-image-alt-hint"
            onChange={(e) => setAlt(e.target.value)}
          />
          <p id="email-image-alt-hint" className="email-template-editor__hint">
            <FormattedMessage id="templates.image_alt_hint" />
          </p>
          <Checkbox checked={decorative} onChange={(e) => setDecorative(e.target.checked)}>
            <FormattedMessage id="templates.image_decorative" />
          </Checkbox>
        </div>
      )}
    </Modal>
  );
};

export default ImagePicker;
