import {
  courseInsightsElements,
  courseInsightsSummary,
  insightsStudioPath,
  learnerInsightsEnabled,
  type CourseInsightsElements,
  type CourseInsightsSummary,
  type InsightsPeriod,
} from '@/services/learnerInsights';
import { studioUrl } from '@/services/studio';
import {
  Alert,
  Button,
  Card,
  Col,
  Descriptions,
  Row,
  Select,
  Skeleton,
  Statistic,
  Typography,
} from 'antd';
import React, { useCallback, useEffect, useState } from 'react';
import { useIntl } from 'umi';

const TOP_SECTIONS = 5;
const PERIODS: InsightsPeriod[] = [7, 30, 90];

type State =
  | { mode: 'loading' }
  | { mode: 'off' }
  | { mode: 'forbidden' }
  | { mode: 'error' }
  | { mode: 'loaded'; summary: CourseInsightsSummary; elements: CourseInsightsElements };

const statusOf = (error: unknown): number | undefined =>
  (error as { response?: { status?: number } } | undefined)?.response?.status;

/**
 * The course tab "Insights": a short summary (status distribution, completion, the top five confusing sections)
 * and a link to the full page in the author studio. One full implementation, not two. Rates are written as
 * numbers; nothing here relies on colour.
 */
const InsightsTab: React.FC<{ courseId: number | string }> = ({ courseId }) => {
  const intl = useIntl();
  const t = (id: string, values?: Record<string, string | number>) =>
    intl.formatMessage({ id }, values);
  const [period, setPeriod] = useState<InsightsPeriod>(30);
  const [state, setState] = useState<State>({ mode: 'loading' });
  const percent = (rate: number) =>
    intl.formatNumber(rate, { style: 'percent', maximumFractionDigits: 0 });

  const load = useCallback(async () => {
    setState({ mode: 'loading' });
    try {
      const enabled = await learnerInsightsEnabled().catch(() => true);
      if (!enabled) {
        setState({ mode: 'off' });
        return;
      }
      const [summary, elements] = await Promise.all([
        courseInsightsSummary(courseId, period),
        courseInsightsElements(courseId, period),
      ]);
      setState({ mode: 'loaded', summary: summary.data, elements: elements.data });
    } catch (error) {
      setState({ mode: statusOf(error) === 403 ? 'forbidden' : 'error' });
    }
  }, [courseId, period]);

  useEffect(() => {
    void load();
  }, [load]);

  const open = (
    <Button
      type="primary"
      href={studioUrl(insightsStudioPath(courseId))}
      target="_blank"
      rel="noopener"
    >
      {t('course_insights_open')}
      <span className="ant-typography-secondary"> {t('course_insights_new_tab')}</span>
    </Button>
  );

  const periodSelect = (
    <Select<InsightsPeriod>
      aria-label={t('course_insights_period')}
      value={period}
      onChange={setPeriod}
      style={{ minWidth: 180 }}
      options={PERIODS.map((p) => ({
        value: p,
        label: t('course_insights_period_days', { days: p }),
      }))}
    />
  );

  const body = (): React.ReactNode => {
    switch (state.mode) {
      case 'loading':
        return <Skeleton active paragraph={{ rows: 4 }} aria-busy="true" />;
      case 'off':
        return <Alert type="info" showIcon message={t('course_insights_off')} />;
      case 'forbidden':
        return <Alert type="warning" showIcon message={t('course_insights_forbidden')} />;
      case 'error':
        return (
          <Alert
            type="error"
            showIcon
            message={t('course_insights_error')}
            action={<Button onClick={() => void load()}>{t('course_insights_retry')}</Button>}
          />
        );
      case 'loaded': {
        const { summary, elements } = state;
        if (summary.smallGroup || !summary.learners || !summary.statuses) {
          return (
            <Alert
              type="info"
              showIcon
              message={t('course_insights_small_group', {
                enrolled: summary.enrolled,
                n: summary.minGroupSize,
              })}
            />
          );
        }
        const shown = elements.elements.filter((e) => !e.hidden);
        const hidden = elements.elements.length - shown.length;
        return (
          <>
            <Row gutter={[16, 16]}>
              <Col xs={12} md={6}>
                <Statistic
                  title={t('course_insights_enrolled')}
                  value={summary.learners.enrolled}
                />
              </Col>
              <Col xs={12} md={6}>
                <Statistic
                  title={t('course_insights_completed')}
                  value={summary.learners.completed}
                />
              </Col>
              <Col xs={12} md={6}>
                <Statistic
                  title={t('course_insights_completion_rate')}
                  value={percent(summary.learners.completionRate)}
                />
              </Col>
              <Col xs={12} md={6}>
                <Statistic title={t('course_insights_active')} value={summary.learners.active} />
              </Col>
            </Row>
            <Descriptions
              style={{ marginTop: 24 }}
              size="small"
              bordered
              column={{ xs: 1, sm: 3 }}
              title={t('course_insights_statuses')}
            >
              <Descriptions.Item label={t('course_insights_on_track')}>
                {summary.statuses.on_track}
              </Descriptions.Item>
              <Descriptions.Item label={t('course_insights_struggling')}>
                {summary.statuses.struggling}
              </Descriptions.Item>
              <Descriptions.Item label={t('course_insights_at_risk')}>
                {summary.statuses.at_risk}
              </Descriptions.Item>
            </Descriptions>
            <Typography.Title level={5} style={{ marginTop: 24 }}>
              {t('course_insights_top_sections')}
            </Typography.Title>
            {shown.length === 0 ? (
              <Typography.Paragraph type="secondary">
                {t('course_insights_no_sections')}
              </Typography.Paragraph>
            ) : (
              <ol data-testid="insights-top-sections">
                {shown.slice(0, TOP_SECTIONS).map((e) => (
                  <li key={e.elementKey}>
                    <strong>{e.label}</strong>
                    {': '}
                    {t('course_insights_section_line', {
                      rate: percent(e.struggleRate ?? 0),
                      active: e.activeLearners ?? 0,
                    })}
                  </li>
                ))}
              </ol>
            )}
            {hidden > 0 && (
              <Typography.Paragraph type="secondary">
                {t('course_insights_hidden_sections', { count: hidden, n: elements.minGroupSize })}
              </Typography.Paragraph>
            )}
            <Typography.Paragraph type="secondary">
              {t('course_insights_privacy', { n: summary.minGroupSize })}
            </Typography.Paragraph>
          </>
        );
      }
      default:
        return null;
    }
  };

  return (
    <Card
      title={t('course_insights')}
      extra={
        <>
          {periodSelect} {open}
        </>
      }
    >
      <div aria-live="polite" aria-busy={state.mode === 'loading'}>
        {body()}
      </div>
    </Card>
  );
};

export default InsightsTab;
