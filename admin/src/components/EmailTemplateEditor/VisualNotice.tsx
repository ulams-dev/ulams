import { Alert, Button, Modal } from 'antd';
import React from 'react';
import { FormattedMessage } from 'umi';
import type { DiffLine } from './roundTrip';

const SHOWN_LINES = 8;
const CONTEXT = 2;

/** Keeps the changed lines and two lines of context around them. */
export const contextDiff = (diff: DiffLine[]): (DiffLine | { type: 'gap' })[] => {
  const keep = diff.map((line, i) =>
    diff.some((other, j) => other.type !== 'same' && Math.abs(i - j) <= CONTEXT),
  );
  const out: (DiffLine | { type: 'gap' })[] = [];
  diff.forEach((line, i) => {
    if (keep[i]) out.push(line);
    else if (out.length && out[out.length - 1].type !== 'gap') out.push({ type: 'gap' });
  });
  return out;
};

const PREFIX = { same: '  ', del: '- ', add: '+ ' } as const;

export const DiffView: React.FC<{ diff: DiffLine[] }> = ({ diff }) => (
  <pre className="email-template-editor__diff" data-testid="mjml-diff" tabIndex={0}>
    {contextDiff(diff).map((line, i) =>
      line.type === 'gap' ? (
        // eslint-disable-next-line react/no-array-index-key
        <div key={`gap-${i}`}>...</div>
      ) : (
        // eslint-disable-next-line react/no-array-index-key
        <div key={`${i}-${line.text}`} className={`email-template-editor__diff-${line.type}`}>
          {PREFIX[line.type]}
          {line.text}
        </div>
      ),
    )}
  </pre>
);

const LineList: React.FC<{ title: React.ReactNode; lines: string[]; testId: string }> = ({
  title,
  lines,
  testId,
}) =>
  lines.length === 0 ? null : (
    <div>
      <strong>{title}</strong>
      <ul data-testid={testId}>
        {lines.slice(0, SHOWN_LINES).map((line, i) => (
          // eslint-disable-next-line react/no-array-index-key
          <li key={`${i}-${line}`}>
            <code>{line}</code>
          </li>
        ))}
        {lines.length > SHOWN_LINES && (
          <li>
            <FormattedMessage
              id="templates.visual_more"
              values={{ count: lines.length - SHOWN_LINES }}
            />
          </li>
        )}
      </ul>
    </div>
  );

export type VisualBlock =
  | { kind: 'html' }
  | { kind: 'error'; message: string }
  | { kind: 'lossy'; lost: string[]; added: string[]; diff: DiffLine[]; exported: string };

/** Why the template stays in Code view, and the only way forward (convert after a diff). */
export const BlockedBanner: React.FC<{ block: VisualBlock; onConvert: () => void }> = ({
  block,
  onConvert,
}) => (
  <Alert
    type={block.kind === 'error' ? 'error' : 'warning'}
    showIcon
    role="status"
    message={
      <FormattedMessage
        id={block.kind === 'error' ? 'templates.visual_load_failed' : 'templates.visual_blocked'}
      />
    }
    description={
      block.kind === 'html' ? (
        <FormattedMessage id="templates.visual_blocked_html" />
      ) : block.kind === 'error' ? (
        block.message
      ) : (
        <div>
          <p>
            <FormattedMessage id="templates.visual_blocked_lossy" />
          </p>
          <LineList
            title={<FormattedMessage id="templates.visual_lost" />}
            lines={block.lost}
            testId="visual-lost"
          />
          <LineList
            title={<FormattedMessage id="templates.visual_added" />}
            lines={block.added}
            testId="visual-added"
          />
          <Button onClick={onConvert}>
            <FormattedMessage id="templates.visual_convert" />
          </Button>
        </div>
      )
    }
  />
);

export const ConvertDialog: React.FC<{
  diff: DiffLine[] | undefined;
  title: string;
  confirmText: string;
  cancelText: string;
  hint: React.ReactNode;
  onConfirm: () => void;
  onCancel: () => void;
}> = ({ diff, title, confirmText, cancelText, hint, onConfirm, onCancel }) => (
  <Modal
    open={!!diff}
    title={title}
    okText={confirmText}
    cancelText={cancelText}
    onOk={onConfirm}
    onCancel={onCancel}
    width={800}
    destroyOnClose
  >
    <p>{hint}</p>
    {diff && <DiffView diff={diff} />}
  </Modal>
);
