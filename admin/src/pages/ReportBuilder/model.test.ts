import type { ReportDataset } from '@/services/ulams/reporting';
import {
  emptyState,
  filterValue,
  fromDefinition,
  functionsFor,
  missingParts,
  operatorsFor,
  outputNames,
  toDefinition,
} from './model';

const dataset: ReportDataset = {
  key: 'assignments',
  label: 'Training assignments',
  description: '',
  permission: 'compliance_report',
  subject_column: 'user_id',
  aggregate_only: false,
  date_columns: ['assigned_at'],
  columns: [
    {
      name: 'course_title',
      type: 'string',
      label: 'Course',
      filterOps: ['eq', 'in', 'contains', 'is_null'],
      groupable: true,
      aggregatable: false,
      personal: false,
      enumValues: [],
    },
    {
      name: 'user_id',
      type: 'int',
      label: 'Learner id',
      filterOps: ['eq', 'in', 'between'],
      groupable: true,
      aggregatable: true,
      personal: false,
      enumValues: [],
    },
    {
      name: 'user_email',
      type: 'string',
      label: 'E-mail',
      filterOps: ['eq', 'contains'],
      groupable: false,
      aggregatable: false,
      personal: true,
      enumValues: [],
    },
    {
      name: 'assigned_at',
      type: 'datetime',
      label: 'Assigned',
      filterOps: ['gte', 'between'],
      groupable: true,
      aggregatable: false,
      personal: false,
      enumValues: [],
    },
  ],
};

describe('report builder model', () => {
  it('builds a row-level definition with typed filter values', () => {
    const state = {
      ...emptyState('assignments'),
      columns: ['course_title'],
      filters: [
        { column: 'user_id', op: 'in', value: '1, 2,3' },
        { column: 'course_title', op: 'is_null', value: 'false' },
      ],
      limit: '50',
    };

    expect(toDefinition(state, dataset)).toEqual({
      dataset: 'assignments',
      columns: ['course_title'],
      filters: [
        { column: 'user_id', op: 'in', value: [1, 2, 3] },
        { column: 'course_title', op: 'is_null', value: false },
      ],
      limit: 50,
    });
  });

  it('builds a grouped definition with a bucket, a preset, a sort and drops empty rows', () => {
    const state = {
      ...emptyState('assignments'),
      mode: 'grouped' as const,
      groupBy: [
        { column: 'course_title', bucket: '' },
        { column: 'assigned_at', bucket: 'month' },
        { column: '', bucket: '' },
      ],
      aggregates: [
        { fn: 'count_distinct', column: 'user_id', as: 'learners' },
        { fn: 'count', column: '', as: 'rows' },
        { fn: 'count', column: '', as: '' },
      ],
      dateColumn: 'assigned_at',
      datePreset: 'last_90_days',
      sortColumn: 'learners',
      sortDir: 'desc' as const,
    };

    expect(toDefinition(state, dataset)).toEqual({
      dataset: 'assignments',
      groupBy: ['course_title', { column: 'assigned_at', bucket: 'month' }],
      aggregates: [
        { fn: 'count_distinct', column: 'user_id', as: 'learners' },
        { fn: 'count', as: 'rows' },
      ],
      dateRange: { column: 'assigned_at', preset: 'last_90_days' },
      sort: [{ column: 'learners', dir: 'desc' }],
    });
    expect(outputNames(state, dataset)).toEqual([
      'course_title',
      'assigned_at_month',
      'learners',
      'rows',
    ]);
  });

  it('uses custom dates without a preset', () => {
    const state = {
      ...emptyState('assignments'),
      columns: ['course_title'],
      dateColumn: 'assigned_at',
      datePreset: 'custom',
      from: '2026-01-01',
      to: '',
    };

    expect(toDefinition(state, dataset).dateRange).toEqual({
      column: 'assigned_at',
      from: '2026-01-01',
    });
  });

  it('turns a stored definition back into the state it came from', () => {
    const state = {
      ...emptyState('assignments'),
      mode: 'grouped' as const,
      groupBy: [{ column: 'assigned_at', bucket: 'week' }],
      aggregates: [{ fn: 'count', column: '', as: 'n' }],
      filters: [{ column: 'user_id', op: 'between', value: '1,9' }],
      dateColumn: 'assigned_at',
      datePreset: 'this_year',
      sortColumn: 'n',
      sortDir: 'asc' as const,
      limit: '10',
    };

    expect(fromDefinition(toDefinition(state, dataset), dataset)).toEqual(state);
  });

  it('knows what is missing before a preview', () => {
    expect(missingParts(emptyState('assignments'))).toEqual(['columns']);
    expect(missingParts({ ...emptyState('assignments'), columns: ['course_title'] })).toEqual([]);
    expect(missingParts({ ...emptyState('assignments'), mode: 'grouped' })).toEqual(['groups']);
    expect(
      missingParts({
        ...emptyState('assignments'),
        mode: 'grouped',
        aggregates: [{ fn: 'count', column: '', as: '' }],
      }),
    ).toEqual(['groups', 'aggregateNames']);
  });

  it('offers only the operators and functions a column allows', () => {
    expect(operatorsFor(dataset.columns[1])).toEqual(['eq', 'in', 'between']);
    expect(functionsFor(dataset.columns[1])).toEqual([
      'count',
      'count_distinct',
      'sum',
      'avg',
      'min',
      'max',
    ]);
    expect(functionsFor(dataset.columns[0])).toEqual(['count', 'count_distinct']);
    expect(functionsFor(dataset.columns[2])).toEqual([]);
    expect(functionsFor(dataset.columns[3])).toEqual(['count', 'count_distinct', 'min', 'max']);
    expect(functionsFor(undefined)).toEqual(['count']);
  });

  it('parses list and boolean values', () => {
    expect(filterValue(dataset.columns[1], 'between', '5, 10')).toEqual([5, 10]);
    expect(filterValue(dataset.columns[0], 'eq', ' Safety ')).toBe('Safety');
    expect(filterValue(dataset.columns[0], 'is_null', 'true')).toBe(true);
  });
});
