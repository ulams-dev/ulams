import type {
  ReportDataset,
  ReportMeta,
  ReportResult,
  SavedReport,
} from '@/services/ulams/reporting';
import {
  deleteSavedReport,
  downloadSavedReport,
  listDatasets,
  listSavedReports,
  previewReport,
  saveReport,
  updateSavedReport,
} from '@/services/ulams/reporting';
import { PageContainer } from '@ant-design/pro-layout';
import {
  Alert,
  Button,
  Checkbox,
  Input,
  InputNumber,
  Modal,
  Popconfirm,
  Radio,
  Select,
  Space,
  Table,
  Tag,
  Typography,
} from 'antd';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useIntl } from 'umi';
import type { BuilderState } from './model';
import {
  BUCKETS,
  emptyState,
  fromDefinition,
  functionsFor,
  missingParts,
  operatorsFor,
  outputNames,
  PRESETS,
  toDefinition,
} from './model';
import './style.less';

type Problem = { path: string; message: string };

const errorsOf = (response: any): Problem[] => {
  const errors = response?.errors ?? response?.data?.errors;
  if (errors && typeof errors === 'object') {
    return Object.entries(errors).flatMap(([path, messages]) =>
      (messages as string[]).map((message) => ({ path, message })),
    );
  }
  return [{ path: '', message: response?.message ?? response?.data?.message ?? String(response) }];
};

const save = (blob: Blob, name: string) => {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  link.click();
  URL.revokeObjectURL(url);
};

const ReportBuilder: React.FC = () => {
  const intl = useIntl();
  const t = useCallback(
    (id: string, values?: Record<string, any>) =>
      intl.formatMessage({ id: `reportBuilder.${id}` }, values),
    [intl],
  );

  const [datasets, setDatasets] = useState<ReportDataset[]>([]);
  const [loading, setLoading] = useState(true);
  const [state, setState] = useState<BuilderState>(emptyState(''));
  const [result, setResult] = useState<ReportResult | null>(null);
  const [meta, setMeta] = useState<ReportMeta | null>(null);
  const [problems, setProblems] = useState<Problem[]>([]);
  const [running, setRunning] = useState(false);
  const [saved, setSaved] = useState<SavedReport[]>([]);
  const [opened, setOpened] = useState<SavedReport | null>(null);
  const [saveOpen, setSaveOpen] = useState(false);
  const [form, setForm] = useState({
    name: '',
    description: '',
    visibility: 'private' as 'private' | 'roles',
    roles: [] as string[],
  });
  const [notice, setNotice] = useState('');

  const dataset = useMemo(
    () => datasets.find((d) => d.key === state.dataset),
    [datasets, state.dataset],
  );
  const set = (patch: Partial<BuilderState>) => setState((s) => ({ ...s, ...patch }));

  const refreshSaved = useCallback(async () => {
    const response = await listSavedReports();
    setSaved(response?.success ? response.data : []);
  }, []);

  useEffect(() => {
    (async () => {
      const response = await listDatasets();
      const list = response?.success ? response.data : [];
      setDatasets(list);
      if (list[0]) setState(emptyState(list[0].key));
      await refreshSaved();
      setLoading(false);
    })();
  }, [refreshSaved]);

  const chooseDataset = (key: string) => {
    setState(emptyState(key));
    setResult(null);
    setMeta(null);
    setProblems([]);
    setOpened(null);
  };

  const preview = async () => {
    if (!dataset) return;
    setRunning(true);
    setProblems([]);
    setNotice('');
    const response: any = await previewReport(toDefinition(state, dataset)).catch(
      (e: any) => e?.response?.data ?? e?.data ?? e,
    );
    setRunning(false);
    if (response?.success) {
      setResult(response.data);
      setMeta(response.meta ?? null);
    } else {
      setResult(null);
      setMeta(null);
      setProblems(errorsOf(response));
    }
  };

  const openSaved = (report: SavedReport) => {
    const target = datasets.find((d) => d.key === report.dataset);
    if (!target) return;
    setState(fromDefinition(report.definition, target));
    setOpened(report);
    setResult(null);
    setMeta(null);
    setProblems([]);
    setNotice(t('opened', { name: report.name }));
  };

  const startSave = () => {
    setForm(
      opened
        ? {
            name: opened.name,
            description: opened.description ?? '',
            visibility: opened.visibility,
            roles: opened.shared_roles,
          }
        : { name: '', description: '', visibility: 'private', roles: [] },
    );
    setProblems([]);
    setSaveOpen(true);
  };

  const submitSave = async (asNew: boolean) => {
    if (!dataset) return;
    const body = {
      name: form.name,
      description: form.description,
      visibility: form.visibility,
      shared_roles: form.visibility === 'roles' ? form.roles : [],
      definition: toDefinition(state, dataset),
    };
    const response: any = await (opened && !asNew
      ? updateSavedReport(opened.id, body)
      : saveReport(body)
    ).catch((e: any) => e?.response?.data ?? e?.data ?? e);
    if (response?.success) {
      setSaveOpen(false);
      setOpened(response.data);
      setNotice(t('saved', { name: response.data.name }));
      await refreshSaved();
    } else {
      setProblems(errorsOf(response));
    }
  };

  const download = async (report: SavedReport, format: 'csv' | 'jsonl') => {
    const blob = await downloadSavedReport(report.id, format);
    save(blob, `${report.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase() || 'report'}.${format}`);
    setNotice(t('downloaded', { name: report.name, format: format.toUpperCase() }));
  };

  const remove = async (report: SavedReport) => {
    await deleteSavedReport(report.id);
    if (opened?.id === report.id) setOpened(null);
    setNotice(t('deleted', { name: report.name }));
    await refreshSaved();
  };

  if (loading)
    return (
      <PageContainer>
        <div className="report-builder" aria-busy="true">
          {t('loading')}
        </div>
      </PageContainer>
    );
  if (!dataset) {
    return (
      <PageContainer>
        <div className="report-builder">
          <Alert type="info" showIcon message={t('noDatasets')} />
        </div>
      </PageContainer>
    );
  }

  const missing = missingParts(state);
  const columnOptions = dataset.columns.map((c) => ({ value: c.name, label: c.label }));
  const column = (name: string) => dataset.columns.find((c) => c.name === name);
  const outputs = outputNames(state, dataset);
  const sortOptions = (state.mode === 'rows' ? state.columns : outputs).map((name) => ({
    value: name,
    label: dataset.columns.find((c) => c.name === name)?.label ?? name,
  }));
  const dateOptions = dataset.date_columns.map((name) => ({
    value: name,
    label: column(name)?.label ?? name,
  }));

  const tableColumns = (result?.columns ?? []).map((c) => ({
    title: c.label,
    dataIndex: c.name,
    key: c.name,
    render: (value: unknown) =>
      value === null || value === undefined
        ? ''
        : typeof value === 'boolean'
        ? t(value ? 'yes' : 'no')
        : String(value),
  }));

  return (
    <PageContainer title={t('title')} subTitle={t('subtitle')}>
      <div className="report-builder">
        <div role="status" aria-live="polite" className="report-builder__notice">
          {notice}
        </div>

        <section aria-labelledby="rb-source">
          <h2 id="rb-source">{t('source')}</h2>
          <Space direction="vertical" size="small" style={{ width: '100%' }}>
            <Space wrap>
              <label htmlFor="rb-dataset">{t('dataset')}</label>
              <Select
                id="rb-dataset"
                aria-label={t('dataset')}
                value={state.dataset}
                onChange={chooseDataset}
                style={{ minWidth: 260 }}
                options={datasets.map((d) => ({ value: d.key, label: d.label }))}
              />
            </Space>
            <Typography.Paragraph type="secondary">{dataset.description}</Typography.Paragraph>
            {opened ? <Tag color="blue">{t('editing', { name: opened.name })}</Tag> : null}
            <Radio.Group
              aria-label={t('mode')}
              value={state.mode}
              onChange={(e) => set({ mode: e.target.value, sortColumn: '' })}
              options={[
                { value: 'rows', label: t('modeRows') },
                { value: 'grouped', label: t('modeGrouped'), disabled: false },
              ]}
            />
          </Space>
        </section>

        {state.mode === 'rows' ? (
          <section aria-labelledby="rb-columns">
            <h2 id="rb-columns">{t('columns')}</h2>
            {dataset.aggregate_only ? (
              <Alert type="info" showIcon message={t('aggregateOnly')} />
            ) : null}
            <Checkbox.Group
              value={state.columns}
              onChange={(v) => set({ columns: v as string[] })}
              className="report-builder__columns"
            >
              {dataset.columns.map((c) => (
                <Checkbox key={c.name} value={c.name}>
                  {c.label}
                  {c.personal ? (
                    <span className="report-builder__personal"> ({t('personal')})</span>
                  ) : null}
                </Checkbox>
              ))}
            </Checkbox.Group>
          </section>
        ) : (
          <>
            <section aria-labelledby="rb-groups">
              <h2 id="rb-groups">{t('groupBy')}</h2>
              {state.groupBy.map((g, i) => (
                <Space key={i} wrap className="report-builder__row">
                  <Select
                    aria-label={t('groupColumn', { n: i + 1 })}
                    value={g.column || undefined}
                    placeholder={t('chooseColumn')}
                    style={{ minWidth: 220 }}
                    options={dataset.columns
                      .filter((c) => c.groupable)
                      .map((c) => ({ value: c.name, label: c.label }))}
                    onChange={(value) =>
                      set({
                        groupBy: state.groupBy.map((x, j) =>
                          j === i ? { column: value, bucket: '' } : x,
                        ),
                      })
                    }
                  />
                  {column(g.column)?.type === 'date' || column(g.column)?.type === 'datetime' ? (
                    <Select
                      aria-label={t('bucket', { n: i + 1 })}
                      value={g.bucket}
                      style={{ minWidth: 140 }}
                      options={[
                        { value: '', label: t('noBucket') },
                        ...BUCKETS.map((b) => ({ value: b, label: t(`bucket_${b}`) })),
                      ]}
                      onChange={(value) =>
                        set({
                          groupBy: state.groupBy.map((x, j) =>
                            j === i ? { ...x, bucket: value } : x,
                          ),
                        })
                      }
                    />
                  ) : null}
                  <Button onClick={() => set({ groupBy: state.groupBy.filter((_, j) => j !== i) })}>
                    {t('removeGroup', { n: i + 1 })}
                  </Button>
                </Space>
              ))}
              <Button
                onClick={() => set({ groupBy: [...state.groupBy, { column: '', bucket: '' }] })}
              >
                {t('addGroup')}
              </Button>
            </section>
            <section aria-labelledby="rb-aggregates">
              <h2 id="rb-aggregates">{t('aggregates')}</h2>
              {state.aggregates.map((a, i) => (
                <Space key={i} wrap className="report-builder__row">
                  <Select
                    aria-label={t('aggColumn', { n: i + 1 })}
                    value={a.column || undefined}
                    allowClear
                    placeholder={t('allRows')}
                    style={{ minWidth: 220 }}
                    options={dataset.columns
                      .filter(
                        (c) =>
                          functionsFor(c).length > 0 &&
                          functionsFor(c).some((fn) => fn !== 'count'),
                      )
                      .map((c) => ({ value: c.name, label: c.label }))}
                    onChange={(value) =>
                      set({
                        aggregates: state.aggregates.map((x, j) =>
                          j === i
                            ? {
                                ...x,
                                column: value ?? '',
                                fn: functionsFor(column(value ?? ''))[0] ?? 'count',
                              }
                            : x,
                        ),
                      })
                    }
                  />
                  <Select
                    aria-label={t('aggFunction', { n: i + 1 })}
                    value={a.fn}
                    style={{ minWidth: 170 }}
                    options={functionsFor(column(a.column)).map((fn) => ({
                      value: fn,
                      label: t(`fn_${fn}`),
                    }))}
                    onChange={(value) =>
                      set({
                        aggregates: state.aggregates.map((x, j) =>
                          j === i ? { ...x, fn: value } : x,
                        ),
                      })
                    }
                  />
                  <Input
                    aria-label={t('aggName', { n: i + 1 })}
                    placeholder={t('aggNamePlaceholder')}
                    value={a.as}
                    style={{ width: 180 }}
                    maxLength={40}
                    onChange={(e) =>
                      set({
                        aggregates: state.aggregates.map((x, j) =>
                          j === i
                            ? { ...x, as: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_') }
                            : x,
                        ),
                      })
                    }
                  />
                  <Button
                    onClick={() => set({ aggregates: state.aggregates.filter((_, j) => j !== i) })}
                  >
                    {t('removeAggregate', { n: i + 1 })}
                  </Button>
                </Space>
              ))}
              <Button
                onClick={() =>
                  set({ aggregates: [...state.aggregates, { fn: 'count', column: '', as: '' }] })
                }
              >
                {t('addAggregate')}
              </Button>
            </section>
          </>
        )}

        <section aria-labelledby="rb-filters">
          <h2 id="rb-filters">{t('filters')}</h2>
          {state.filters.map((f, i) => {
            const target = column(f.column);
            return (
              <Space key={i} wrap className="report-builder__row">
                <Select
                  aria-label={t('filterColumn', { n: i + 1 })}
                  value={f.column || undefined}
                  placeholder={t('chooseColumn')}
                  style={{ minWidth: 220 }}
                  options={columnOptions}
                  onChange={(value) =>
                    set({
                      filters: state.filters.map((x, j) =>
                        j === i
                          ? { column: value, op: operatorsFor(column(value))[0] ?? 'eq', value: '' }
                          : x,
                      ),
                    })
                  }
                />
                <Select
                  aria-label={t('filterOperator', { n: i + 1 })}
                  value={f.op}
                  style={{ minWidth: 160 }}
                  options={operatorsFor(target).map((op) => ({ value: op, label: t(`op_${op}`) }))}
                  onChange={(value) =>
                    set({
                      filters: state.filters.map((x, j) => (j === i ? { ...x, op: value } : x)),
                    })
                  }
                />
                {f.op === 'is_null' ? (
                  <Select
                    aria-label={t('filterValue', { n: i + 1 })}
                    value={f.value || 'true'}
                    style={{ minWidth: 120 }}
                    options={[
                      { value: 'true', label: t('yes') },
                      { value: 'false', label: t('no') },
                    ]}
                    onChange={(value) =>
                      set({ filters: state.filters.map((x, j) => (j === i ? { ...x, value } : x)) })
                    }
                  />
                ) : target?.type === 'enum' && (f.op === 'eq' || f.op === 'neq') ? (
                  <Select
                    aria-label={t('filterValue', { n: i + 1 })}
                    value={f.value || undefined}
                    style={{ minWidth: 160 }}
                    options={target.enumValues.map((v) => ({ value: v, label: v }))}
                    onChange={(value) =>
                      set({ filters: state.filters.map((x, j) => (j === i ? { ...x, value } : x)) })
                    }
                  />
                ) : (
                  <Input
                    aria-label={t('filterValue', { n: i + 1 })}
                    value={f.value}
                    style={{ width: 220 }}
                    placeholder={
                      f.op === 'in' || f.op === 'not_in' || f.op === 'between'
                        ? t('listHint')
                        : target?.type === 'date' || target?.type === 'datetime'
                        ? 'YYYY-MM-DD'
                        : ''
                    }
                    onChange={(e) =>
                      set({
                        filters: state.filters.map((x, j) =>
                          j === i ? { ...x, value: e.target.value } : x,
                        ),
                      })
                    }
                  />
                )}
                <Button onClick={() => set({ filters: state.filters.filter((_, j) => j !== i) })}>
                  {t('removeFilter', { n: i + 1 })}
                </Button>
              </Space>
            );
          })}
          <Button
            onClick={() =>
              set({ filters: [...state.filters, { column: '', op: 'eq', value: '' }] })
            }
          >
            {t('addFilter')}
          </Button>
        </section>

        <section aria-labelledby="rb-range">
          <h2 id="rb-range">{t('dateRange')}</h2>
          <Space wrap>
            <Select
              aria-label={t('dateColumn')}
              value={state.dateColumn || undefined}
              allowClear
              placeholder={t('noDateRange')}
              style={{ minWidth: 220 }}
              options={dateOptions}
              onChange={(value) =>
                set({
                  dateColumn: value ?? '',
                  datePreset: value ? state.datePreset || 'last_90_days' : '',
                })
              }
            />
            {state.dateColumn ? (
              <Select
                aria-label={t('datePreset')}
                value={state.datePreset}
                style={{ minWidth: 200 }}
                options={[
                  ...PRESETS.map((p) => ({ value: p, label: t(`preset_${p}`) })),
                  { value: 'custom', label: t('preset_custom') },
                ]}
                onChange={(value) => set({ datePreset: value })}
              />
            ) : null}
            {state.dateColumn && state.datePreset === 'custom' ? (
              <>
                <label htmlFor="rb-from">{t('from')}</label>
                <Input
                  id="rb-from"
                  type="date"
                  value={state.from}
                  onChange={(e) => set({ from: e.target.value })}
                  style={{ width: 170 }}
                />
                <label htmlFor="rb-to">{t('to')}</label>
                <Input
                  id="rb-to"
                  type="date"
                  value={state.to}
                  onChange={(e) => set({ to: e.target.value })}
                  style={{ width: 170 }}
                />
              </>
            ) : null}
          </Space>
        </section>

        <section aria-labelledby="rb-order">
          <h2 id="rb-order">{t('order')}</h2>
          <Space wrap>
            <Select
              aria-label={t('sortColumn')}
              value={state.sortColumn || undefined}
              allowClear
              placeholder={t('noSort')}
              style={{ minWidth: 220 }}
              options={sortOptions}
              onChange={(value) => set({ sortColumn: value ?? '' })}
            />
            <Select
              aria-label={t('sortDirection')}
              value={state.sortDir}
              style={{ minWidth: 140 }}
              options={[
                { value: 'asc', label: t('asc') },
                { value: 'desc', label: t('desc') },
              ]}
              onChange={(value) => set({ sortDir: value })}
            />
            <label htmlFor="rb-limit">{t('limit')}</label>
            <InputNumber
              id="rb-limit"
              min={1}
              max={1000000}
              value={state.limit ? Number(state.limit) : null}
              onChange={(value) => set({ limit: value ? String(value) : '' })}
            />
          </Space>
        </section>

        <Space wrap className="report-builder__actions">
          <Button type="primary" onClick={preview} loading={running} disabled={missing.length > 0}>
            {t('preview')}
          </Button>
          <Button onClick={startSave} disabled={missing.length > 0}>
            {opened ? t('saveChanges') : t('save')}
          </Button>
          {missing.length ? (
            <span className="report-builder__hint">{t(`missing_${missing[0]}`)}</span>
          ) : null}
        </Space>

        {problems.length ? (
          <Alert
            type="error"
            showIcon
            role="alert"
            message={t('problems')}
            description={
              <ul>
                {problems.map((p, i) => (
                  <li key={i}>
                    {p.path ? <code>{p.path}</code> : null} {p.message}
                  </li>
                ))}
              </ul>
            }
          />
        ) : null}

        {result ? (
          <section aria-labelledby="rb-result">
            <h2 id="rb-result">{t('result')}</h2>
            {meta ? (
              <p role="status" className="report-builder__count">
                {meta.truncated
                  ? t('showingSome', { shown: meta.preview_rows, total: meta.total_rows })
                  : t('showingAll', { total: meta.total_rows })}
              </p>
            ) : null}
            {meta && meta.withheld_groups > 0 ? (
              <Alert
                type="warning"
                showIcon
                className="report-builder__withheld"
                message={t('withheldTitle')}
                description={t('withheldText', {
                  groups: meta.withheld_groups,
                  min: meta.min_group_size,
                })}
              />
            ) : null}
            <Table
              size="small"
              pagination={false}
              rowKey={(_, i) => String(i)}
              columns={tableColumns}
              dataSource={result.rows}
              rowClassName={(row) => (row._withheld ? 'report-builder__withheld-row' : '')}
              scroll={{ x: true }}
              locale={{ emptyText: t('noRows') }}
              aria-label={t('result')}
            />
          </section>
        ) : null}

        <section aria-labelledby="rb-saved">
          <h2 id="rb-saved">{t('savedReports')}</h2>
          <Table
            size="small"
            pagination={false}
            rowKey="id"
            dataSource={saved}
            locale={{ emptyText: t('noSaved') }}
            aria-label={t('savedReports')}
            columns={[
              { title: t('name'), dataIndex: 'name' },
              {
                title: t('dataset'),
                dataIndex: 'dataset',
                render: (d: string) => datasets.find((x) => x.key === d)?.label ?? d,
              },
              {
                title: t('sharing'),
                key: 'sharing',
                render: (_: unknown, r: SavedReport) =>
                  r.visibility === 'roles'
                    ? t('sharedWith', { roles: r.shared_roles.join(', ') })
                    : t('private'),
              },
              {
                title: t('status'),
                key: 'status',
                render: (_: unknown, r: SavedReport) =>
                  r.needs_repair ? (
                    <Tag color="red">{t('needsRepair')}</Tag>
                  ) : r.runnable ? (
                    <Tag>{t('ready')}</Tag>
                  ) : (
                    <Tag>{t('cannotRun')}</Tag>
                  ),
              },
              {
                title: t('lastRun'),
                dataIndex: 'last_run_at',
                render: (v: string | null) =>
                  v ? new Date(v).toLocaleString(intl.locale) : t('never'),
              },
              {
                title: t('actions'),
                key: 'actions',
                render: (_: unknown, r: SavedReport) => (
                  <Space wrap>
                    <Button
                      size="small"
                      onClick={() => openSaved(r)}
                      disabled={!datasets.some((d) => d.key === r.dataset)}
                    >
                      {t('open', { name: r.name })}
                    </Button>
                    <Button size="small" onClick={() => download(r, 'csv')} disabled={!r.runnable}>
                      {t('exportCsv', { name: r.name })}
                    </Button>
                    <Button
                      size="small"
                      onClick={() => download(r, 'jsonl')}
                      disabled={!r.runnable}
                    >
                      {t('exportJsonl', { name: r.name })}
                    </Button>
                    {r.can_edit ? (
                      <Popconfirm
                        title={t('deleteConfirm', { name: r.name })}
                        okText={t('delete')}
                        cancelText={t('cancel')}
                        onConfirm={() => remove(r)}
                      >
                        <Button size="small" danger>
                          {t('deleteReport', { name: r.name })}
                        </Button>
                      </Popconfirm>
                    ) : null}
                  </Space>
                ),
              },
            ]}
          />
        </section>

        <Modal
          open={saveOpen}
          title={opened ? t('saveChanges') : t('save')}
          onCancel={() => setSaveOpen(false)}
          footer={
            <Space>
              <Button onClick={() => setSaveOpen(false)}>{t('cancel')}</Button>
              {opened ? (
                <Button onClick={() => submitSave(true)} disabled={!form.name.trim()}>
                  {t('saveAsNew')}
                </Button>
              ) : null}
              <Button type="primary" onClick={() => submitSave(false)} disabled={!form.name.trim()}>
                {opened ? t('saveChanges') : t('save')}
              </Button>
            </Space>
          }
        >
          <Space direction="vertical" style={{ width: '100%' }}>
            <label htmlFor="rb-name">{t('name')}</label>
            <Input
              id="rb-name"
              value={form.name}
              maxLength={160}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
            <label htmlFor="rb-description">{t('description')}</label>
            <Input.TextArea
              id="rb-description"
              value={form.description}
              rows={2}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
            />
            <Radio.Group
              aria-label={t('sharing')}
              value={form.visibility}
              onChange={(e) => setForm({ ...form, visibility: e.target.value })}
              options={[
                { value: 'private', label: t('private') },
                { value: 'roles', label: t('shareWithRoles') },
              ]}
            />
            {form.visibility === 'roles' ? (
              <>
                <label htmlFor="rb-roles">{t('roles')}</label>
                <Select
                  id="rb-roles"
                  mode="tags"
                  value={form.roles}
                  onChange={(roles) => setForm({ ...form, roles })}
                  tokenSeparators={[',']}
                  placeholder={t('rolesHint')}
                />
                <Typography.Text type="secondary">{t('shareNote')}</Typography.Text>
              </>
            ) : null}
            {problems.length ? (
              <Alert
                type="error"
                showIcon
                role="alert"
                message={problems.map((p) => `${p.path} ${p.message}`.trim()).join(' ')}
              />
            ) : null}
          </Space>
        </Modal>
      </div>
    </PageContainer>
  );
};

export default ReportBuilder;
