import { deleteRecordedReviewTime } from '@/services/authoringMetrics';
import { Alert, Button, Card } from 'antd';
import React, { useState } from 'react';
import { useIntl } from 'umi';

/**
 * Above the "Authoring metrics" settings: what review time records, said plainly and in full before an admin turns it
 * on, and the action that deletes everything recorded so far (two steps, so it is never one slip of a click).
 */
const ReviewTimeNotice: React.FC = () => {
  const intl = useIntl();
  const t = (id: string, values?: Record<string, string | number>) =>
    intl.formatMessage({ id }, values);
  const [confirming, setConfirming] = useState(false);
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(null);

  const remove = async () => {
    try {
      const response = await deleteRecordedReviewTime();
      setResult({
        ok: true,
        text: t('authoring_metrics_review_deleted', {
          minutes: response.data.minutes,
          days: response.data.days,
        }),
      });
    } catch {
      setResult({ ok: false, text: t('authoring_metrics_review_delete_failed') });
    }
    setConfirming(false);
  };

  return (
    <Card
      className="review-time-notice"
      title={<h2 style={{ margin: 0, fontSize: 16 }}>{t('authoring_metrics_review_title')}</h2>}
      style={{ marginBottom: 16 }}
    >
      <p>{t('authoring_metrics_review_intro')}</p>
      <ul>
        {['measured', 'stored', 'shown', 'kept'].map((key) => (
          <li key={key}>{t(`authoring_metrics_review_${key}`)}</li>
        ))}
      </ul>
      <p>{t('authoring_metrics_review_switch')}</p>
      {confirming ? (
        <div role="group" aria-label={t('authoring_metrics_review_delete')}>
          <p>{t('authoring_metrics_review_delete_confirm')}</p>
          <Button danger onClick={() => void remove()}>
            {t('authoring_metrics_review_delete_yes')}
          </Button>{' '}
          <Button onClick={() => setConfirming(false)}>
            {t('authoring_metrics_review_delete_cancel')}
          </Button>
        </div>
      ) : (
        <Button danger onClick={() => setConfirming(true)}>
          {t('authoring_metrics_review_delete')}
        </Button>
      )}
      <style>{`
        .review-time-notice .ant-btn-dangerous { color: #a8071a; border-color: #a8071a; }
        .review-time-notice .ant-btn-dangerous:hover, .review-time-notice .ant-btn-dangerous:focus-visible { color: #820014; border-color: #820014; }
      `}</style>
      <div role="status" aria-live="polite" style={{ marginTop: 12 }}>
        {result && <Alert type={result.ok ? 'success' : 'error'} showIcon message={result.text} />}
      </div>
    </Card>
  );
};

export default ReviewTimeNotice;
