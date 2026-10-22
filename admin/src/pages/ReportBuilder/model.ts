import type { ReportColumn, ReportDataset, ReportDefinition } from '@/services/ulams/reporting';

/** The editable state of the report builder; `toDefinition` turns it into a report definition (schema v1). */
export type BuilderState = {
  dataset: string;
  mode: 'rows' | 'grouped';
  columns: string[];
  filters: { column: string; op: string; value: string }[];
  dateColumn: string;
  /** a preset name, `custom`, or empty for no date range */
  datePreset: string;
  from: string;
  to: string;
  groupBy: { column: string; bucket: string }[];
  aggregates: { fn: string; column: string; as: string }[];
  sortColumn: string;
  sortDir: 'asc' | 'desc';
  limit: string;
};

export const PRESETS = [
  'last_7_days',
  'last_30_days',
  'last_90_days',
  'last_12_months',
  'this_month',
  'last_month',
  'this_quarter',
  'this_year',
  'last_year',
];
export const BUCKETS = ['day', 'week', 'month', 'quarter', 'year'];
export const FUNCTIONS = ['count', 'count_distinct', 'sum', 'avg', 'min', 'max'];
export const OPERATORS = [
  'eq',
  'neq',
  'in',
  'not_in',
  'lt',
  'lte',
  'gt',
  'gte',
  'between',
  'contains',
  'is_null',
];

export const emptyState = (dataset: string): BuilderState => ({
  dataset,
  mode: 'rows',
  columns: [],
  filters: [],
  dateColumn: '',
  datePreset: '',
  from: '',
  to: '',
  groupBy: [],
  aggregates: [],
  sortColumn: '',
  sortDir: 'asc',
  limit: '',
});

const find = (dataset: ReportDataset, name: string): ReportColumn | undefined =>
  dataset.columns.find((c) => c.name === name);

const scalar = (column: ReportColumn | undefined, raw: string): unknown => {
  const text = raw.trim();
  switch (column?.type) {
    case 'int':
    case 'decimal':
      return text !== '' && !Number.isNaN(Number(text)) ? Number(text) : text;
    case 'bool':
      return text === 'true' ? true : text === 'false' ? false : text;
    default:
      return text;
  }
};

/** The value box holds text: a list for in/not_in and between is comma separated, is_null is true or false. */
export const filterValue = (column: ReportColumn | undefined, op: string, raw: string): unknown => {
  if (op === 'is_null') return raw !== 'false';
  if (op === 'in' || op === 'not_in' || op === 'between') {
    return raw
      .split(',')
      .map((part) => scalar(column, part))
      .filter((v) => v !== '');
  }
  return scalar(column, raw);
};

export const outputNames = (state: BuilderState, dataset: ReportDataset): string[] =>
  state.mode === 'rows'
    ? dataset.columns.map((c) => c.name)
    : [
        ...state.groupBy
          .filter((g) => g.column)
          .map((g) => (g.bucket ? `${g.column}_${g.bucket}` : g.column)),
        ...state.aggregates.filter((a) => a.as).map((a) => a.as),
      ];

export function toDefinition(state: BuilderState, dataset: ReportDataset): ReportDefinition {
  const definition: ReportDefinition = { dataset: state.dataset };
  if (state.mode === 'rows') {
    definition.columns = state.columns;
  } else {
    definition.groupBy = state.groupBy
      .filter((g) => g.column)
      .map((g) => (g.bucket ? { column: g.column, bucket: g.bucket } : g.column));
    definition.aggregates = state.aggregates
      .filter((a) => a.as)
      .map((a) => ({ fn: a.fn, ...(a.column ? { column: a.column } : {}), as: a.as }));
  }
  const filters = state.filters.filter((f) => f.column && f.op);
  if (filters.length) {
    definition.filters = filters.map((f) => ({
      column: f.column,
      op: f.op,
      value: filterValue(find(dataset, f.column), f.op, f.value),
    }));
  }
  if (state.dateColumn && state.datePreset) {
    definition.dateRange =
      state.datePreset === 'custom'
        ? {
            column: state.dateColumn,
            ...(state.from ? { from: state.from } : {}),
            ...(state.to ? { to: state.to } : {}),
          }
        : { column: state.dateColumn, preset: state.datePreset };
  }
  if (state.sortColumn) definition.sort = [{ column: state.sortColumn, dir: state.sortDir }];
  const limit = Number(state.limit);
  if (state.limit && Number.isInteger(limit) && limit > 0) definition.limit = limit;

  return definition;
}

/** The builder state of a stored definition, so a saved report can be opened and changed. */
export function fromDefinition(definition: ReportDefinition, dataset: ReportDataset): BuilderState {
  const state = emptyState(definition.dataset);
  const grouped = Boolean(definition.groupBy?.length || definition.aggregates?.length);
  state.mode = grouped ? 'grouped' : 'rows';
  state.columns = definition.columns ?? [];
  state.groupBy = (definition.groupBy ?? []).map((g) =>
    typeof g === 'string' ? { column: g, bucket: '' } : { column: g.column, bucket: g.bucket },
  );
  state.aggregates = (definition.aggregates ?? []).map((a) => ({
    fn: a.fn,
    column: a.column ?? '',
    as: a.as,
  }));
  state.filters = (definition.filters ?? []).map((f) => ({
    column: f.column,
    op: f.op,
    value: Array.isArray(f.value)
      ? f.value.join(',')
      : f.value === undefined || f.value === null
        ? ''
        : String(f.value),
  }));
  if (definition.dateRange) {
    state.dateColumn = definition.dateRange.column;
    state.datePreset = definition.dateRange.preset ?? 'custom';
    state.from = definition.dateRange.from ?? '';
    state.to = definition.dateRange.to ?? '';
  }
  const sort = definition.sort?.[0];
  if (sort) {
    state.sortColumn = sort.column;
    state.sortDir = sort.dir ?? 'asc';
  }
  state.limit = definition.limit ? String(definition.limit) : '';
  void dataset;

  return state;
}

/** What is missing before a preview can be asked for (the server still has the last word). */
export function missingParts(state: BuilderState): ('columns' | 'groups' | 'aggregateNames')[] {
  if (state.mode === 'rows') return state.columns.length ? [] : ['columns'];
  const missing: ('columns' | 'groups' | 'aggregateNames')[] = [];
  if (!state.groupBy.some((g) => g.column) && !state.aggregates.some((a) => a.as))
    missing.push('groups');
  if (state.aggregates.some((a) => !a.as)) missing.push('aggregateNames');
  return missing;
}

/** Operators a column accepts, in the order of OPERATORS. */
export const operatorsFor = (column: ReportColumn | undefined): string[] =>
  OPERATORS.filter((op) => column?.filterOps.includes(op));

/** Functions a column accepts: count always, count_distinct on any column that is not personal, sum and avg on numbers, min and max on numbers and dates. */
export const functionsFor = (column: ReportColumn | undefined): string[] => {
  if (!column) return ['count'];
  if (column.personal) return [];
  const numeric = column.aggregatable;
  const temporal = column.type === 'date' || column.type === 'datetime';
  return FUNCTIONS.filter(
    (fn) =>
      fn === 'count' ||
      fn === 'count_distinct' ||
      (numeric && (fn === 'sum' || fn === 'avg' || fn === 'min' || fn === 'max')) ||
      (temporal && (fn === 'min' || fn === 'max')),
  );
};
