import {
  courseMetrics,
  exportCourseMetrics,
  type CourseMetric,
  type MetricFilter,
} from '@/services/authoringMetrics';
import { PageContainer } from '@ant-design/pro-layout';
import { Alert, Button, Card, Skeleton } from 'antd';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useIntl } from 'umi';
import { duration, elementSplit, saveBlob, usd } from './model';

type State =
  | { mode: 'loading' }
  | { mode: 'forbidden' }
  | { mode: 'error' }
  | { mode: 'loaded'; rows: CourseMetric[] };

const statusOf = (error: unknown): number | undefined =>
  (error as { response?: { status?: number } } | undefined)?.response?.status;

/**
 * Analytics > Authoring: how long each course built with the Course Builder took to produce, what it cost and
 * what became of the AI's elements, one row per course, with the file export. Every figure is text; nothing is
 * compared with another academy or an industry figure. Author time is "not recorded" until review time is
 * recorded, never a zero.
 */
const AuthoringMetrics: React.FC = () => {
  const intl = useIntl();
  const t = (id: string, values?: Record<string, string | number>) =>
    intl.formatMessage({ id }, values);
  const percent = (rate: number) =>
    intl.formatNumber(rate, { style: 'percent', maximumFractionDigits: 0 });
  const [filter, setFilter] = useState<MetricFilter>({});
  const [state, setState] = useState<State>({ mode: 'loading' });
  const [notice, setNotice] = useState('');
  const requestId = useRef(0);

  const load = useCallback(async () => {
    const mine = ++requestId.current;
    setState({ mode: 'loading' });
    try {
      const response = await courseMetrics(filter);
      if (mine === requestId.current) setState({ mode: 'loaded', rows: response.data });
    } catch (error) {
      if (mine === requestId.current) {
        setState({ mode: statusOf(error) === 403 ? 'forbidden' : 'error' });
      }
    }
  }, [filter]);

  useEffect(() => {
    void load();
  }, [load]);

  const download = async (format: 'csv' | 'jsonl') => {
    try {
      const blob = await exportCourseMetrics(format, filter);
      const name = `authoring-metrics.${format}`;
      saveBlob(name, blob);
      setNotice(t('authoring_metrics_downloaded', { name }));
    } catch {
      setNotice(t('authoring_metrics_export_failed'));
    }
  };

  const field = (name: 'from' | 'to') => (
    <label className="authoring-metrics__field">
      <span>{t(`authoring_metrics_${name}`)}</span>
      <input
        type="date"
        name={name}
        value={filter[name] ?? ''}
        onChange={(event) =>
          setFilter({
            ...filter,
            [name]: event.target.value === '' ? undefined : event.target.value,
          })
        }
      />
    </label>
  );

  const body = () => {
    if (state.mode === 'loading') {
      return (
        <div role="status" aria-live="polite">
          <Skeleton active paragraph={{ rows: 3 }} />
          <span className="authoring-metrics__sr">{t('authoring_metrics_loading')}</span>
        </div>
      );
    }
    if (state.mode === 'forbidden') {
      return (
        <Alert type="warning" showIcon role="alert" message={t('authoring_metrics_forbidden')} />
      );
    }
    if (state.mode === 'error') {
      return (
        <Alert
          type="error"
          showIcon
          role="alert"
          message={t('authoring_metrics_error')}
          action={<Button onClick={() => void load()}>{t('authoring_metrics_retry')}</Button>}
        />
      );
    }
    if (state.rows.length === 0) {
      return <p data-state="empty">{t('authoring_metrics_empty')}</p>;
    }
    return (
      <div
        className="authoring-metrics__scroll"
        role="region"
        aria-label={t('authoring_metrics_scroll')}
        tabIndex={0}
      >
        <table className="authoring-metrics__table">
          <caption className="authoring-metrics__sr">{t('authoring_metrics_caption')}</caption>
          <thead>
            <tr>
              <th scope="col">{t('authoring_metrics_col_course')}</th>
              <th scope="col">{t('authoring_metrics_col_generation')}</th>
              <th scope="col">{t('authoring_metrics_col_interview')}</th>
              <th scope="col">{t('authoring_metrics_col_author')}</th>
              <th scope="col">{t('authoring_metrics_col_build')}</th>
              <th scope="col">{t('authoring_metrics_col_maintenance')}</th>
              <th scope="col">{t('authoring_metrics_col_content')}</th>
              <th scope="col">{t('authoring_metrics_col_elements')}</th>
            </tr>
          </thead>
          <tbody>
            {state.rows.map((m) => {
              const split = elementSplit(m);
              return (
                <tr key={m.course_id} data-course={m.course_id}>
                  <th scope="row">
                    <Link to={`/courses/list/${m.course_id}`}>
                      {m.course_title ??
                        t('authoring_metrics_course_fallback', { id: m.course_id })}
                    </Link>
                  </th>
                  <td>{duration(m.time.generation_seconds + m.time.ai_edit_seconds, t)}</td>
                  <td>{duration(m.time.interview_seconds, t)}</td>
                  <td>
                    {m.time.author_minutes === null
                      ? t('authoring_metrics_not_recorded')
                      : duration(m.time.author_minutes * 60, t)}
                  </td>
                  <td>{usd(m.cost.build_micro_usd, intl.locale)}</td>
                  <td>{usd(m.cost.maintenance_micro_usd, intl.locale)}</td>
                  <td>
                    {t('authoring_metrics_content', {
                      lessons: m.counts.lessons,
                      questions: m.counts.questions,
                    })}
                  </td>
                  <td>
                    {split.total === 0
                      ? t('authoring_metrics_no_ai_elements')
                      : t('authoring_metrics_split', {
                          kept: split.kept,
                          revised: split.revised,
                          edited: split.edited,
                          unknown: split.unknown,
                          share: percent(split.keptShare ?? 0),
                        })}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    );
  };

  return (
    <PageContainer title={t('authoring_metrics_title')}>
      <Card className="authoring-metrics">
        <h2>{t('authoring_metrics_title')}</h2>
        <p>{t('authoring_metrics_intro')}</p>
        <p className="authoring-metrics__muted">{t('authoring_metrics_own_figures')}</p>
        <form
          className="authoring-metrics__filters"
          aria-label={t('authoring_metrics_filters')}
          onSubmit={(event) => event.preventDefault()}
        >
          {field('from')}
          {field('to')}
          <Button onClick={() => void download('csv')}>{t('authoring_metrics_export_csv')}</Button>
          <Button onClick={() => void download('jsonl')}>
            {t('authoring_metrics_export_jsonl')}
          </Button>
        </form>
        <div role="status" aria-live="polite" className="authoring-metrics__notice">
          {notice}
        </div>
        {body()}
        <p className="authoring-metrics__muted">{t('authoring_metrics_note')}</p>
      </Card>
      <style>{`
        .authoring-metrics__filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; margin: 16px 0; }
        .authoring-metrics__field { display: flex; flex-direction: column; gap: 4px; }
        .authoring-metrics__field input { min-height: 32px; padding: 4px 8px; border: 1px solid #8c8c8c; border-radius: 4px; }
        .authoring-metrics__scroll { overflow-x: auto; }
        .authoring-metrics__scroll:focus-visible { outline: 2px solid #1d39c4; outline-offset: 2px; }
        .authoring-metrics__table { width: 100%; border-collapse: collapse; }
        .authoring-metrics__table th, .authoring-metrics__table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #d9d9d9; vertical-align: top; }
        .authoring-metrics__table a { color: #0b3d91; text-decoration: underline; }
        .authoring-metrics__muted { color: #595959; }
        .authoring-metrics__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
      `}</style>
    </PageContainer>
  );
};

export default AuthoringMetrics;
