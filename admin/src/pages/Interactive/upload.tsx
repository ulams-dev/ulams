import { UploadOutlined } from '@ant-design/icons';
import { Alert, Button, Checkbox, Input, Modal, Space, Upload, message } from 'antd';
import React, { useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';

/** The origins a 422 asks the author to confirm (the API lists them under `errors.network`). */
export const networkToConfirm = (error: any): string[] =>
  (error?.data?.errors?.network ?? error?.response?.data?.errors?.network ?? []) as string[];

export const errorText = (error: any) =>
  error?.data?.message ||
  (Object.values(error?.data?.errors ?? {}) as string[][])?.flat?.()?.[0] ||
  error?.message ||
  String(error);

/**
 * Upload dialog of a package or of a new version. A package whose manifest lists network origins is
 * accepted only after the author confirms them (the API answers 422 with the list first), so nobody
 * adds a package that calls other sites without having seen where.
 */
export const UploadDialog: React.FC<{
  open: boolean;
  title: React.ReactNode;
  withTitle?: boolean;
  submit: (
    file: File,
    fields: { title?: string; note?: string; acceptNetwork: boolean },
  ) => Promise<void>;
  onClose: () => void;
}> = ({ open, title, withTitle, submit, onClose }) => {
  const intl = useIntl();
  const [file, setFile] = useState<File | null>(null);
  const [name, setName] = useState('');
  const [note, setNote] = useState('');
  const [origins, setOrigins] = useState<string[]>([]);
  const [confirmed, setConfirmed] = useState(false);
  const [busy, setBusy] = useState(false);

  const reset = () => {
    setFile(null);
    setName('');
    setNote('');
    setOrigins([]);
    setConfirmed(false);
  };

  const send = async () => {
    if (!file) return;
    setBusy(true);
    try {
      await submit(file, {
        title: name || undefined,
        note: note || undefined,
        acceptNetwork: confirmed,
      });
      reset();
      onClose();
    } catch (error) {
      const list = networkToConfirm(error);
      if (list.length > 0) setOrigins(list);
      else message.error(errorText(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      open={open}
      title={title}
      onCancel={() => {
        reset();
        onClose();
      }}
      footer={null}
      destroyOnClose
    >
      <Space direction="vertical" style={{ width: '100%' }}>
        <Upload
          accept=".zip"
          maxCount={1}
          beforeUpload={(f) => {
            setFile(f);
            setOrigins([]);
            setConfirmed(false);
            return false;
          }}
          onRemove={() => setFile(null)}
        >
          <Button icon={<UploadOutlined />}>
            <FormattedMessage
              id="interactive.choose_file"
              defaultMessage="Choose a .zip with index.html and ulams-interactive.json"
            />
          </Button>
        </Upload>
        {withTitle && (
          <Input
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder={intl.formatMessage({
              id: 'interactive.title_from_manifest',
              defaultMessage: 'Title (taken from the manifest when empty)',
            })}
          />
        )}
        <Input
          value={note}
          onChange={(e) => setNote(e.target.value)}
          placeholder={intl.formatMessage({
            id: 'interactive.change_note',
            defaultMessage: 'Change note (optional)',
          })}
        />
        {origins.length > 0 && (
          <Alert
            type="warning"
            showIcon
            message={
              <FormattedMessage
                id="interactive.network_title"
                defaultMessage="This package wants to call other sites"
              />
            }
            description={
              <>
                <ul>
                  {origins.map((o) => (
                    <li key={o}>
                      <code>{o}</code>
                    </li>
                  ))}
                </ul>
                <Checkbox checked={confirmed} onChange={(e) => setConfirmed(e.target.checked)}>
                  <FormattedMessage
                    id="interactive.network_confirm"
                    defaultMessage="I have seen these origins and accept them. They only take effect when the tenant setting ulams_interactive.allow_network is on."
                  />
                </Checkbox>
              </>
            }
          />
        )}
        <Button
          type="primary"
          loading={busy}
          disabled={!file || (origins.length > 0 && !confirmed)}
          onClick={send}
        >
          <FormattedMessage id="interactive.upload" defaultMessage="Upload" />
        </Button>
      </Space>
    </Modal>
  );
};
