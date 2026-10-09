import ProTable, { type ProColumns } from '@ant-design/pro-table';
import { format } from 'date-fns';
import React, { useCallback, useMemo, useState } from 'react';
import { FormattedMessage } from 'umi';

import TypeButtonDrawer from '@/components/TypeButtonDrawer';
import { DATETIME_FORMAT } from '@/consts/dates';
import { getTopicStats } from '@/services/ulams/course';
import { TopicStatsKey } from '@/services/ulams/enums';
import { ExportTopicStatsButton } from './ExportTopicStatsButton';

interface TableParams {
  topic_id?: number;
}

type TableColumn = ProColumns<API.GiftQuizTopicStat>;

const staticColumns: TableColumn[] = [
  {
    dataIndex: 'attempt',
    title: <FormattedMessage id="TopicStatistics.giftQuiz.attempt" />,
    hideInSearch: true,
    fixed: 'left',
    width: 100,
  },
  {
    dataIndex: 'email',
    title: <FormattedMessage id="email" />,
    hideInSearch: true,
    fixed: 'left',
  },
  {
    dataIndex: 'user_id',
    title: <FormattedMessage id="user" />,
    hideInSearch: true,
    render: (_n, record) => <TypeButtonDrawer type="App\Models\User" type_id={record.user_id} />,
  },
  {
    dataIndex: 'attempt_date',
    title: <FormattedMessage id="TopicStatistics.giftQuiz.attemptDate" />,
    hideInSearch: true,
    render: (_n, record) =>
      record.attempt_date ? format(new Date(record.attempt_date), DATETIME_FORMAT) : '-',
  },
  {
    dataIndex: 'attempt_time',
    title: <FormattedMessage id="TopicStatistics.giftQuiz.attemptDuration" />,
    hideInSearch: true,
    render: (_n, record) => (record.attempt_time ? `${record.attempt_time}s` : '-'),
  },
  {
    dataIndex: 'summary',
    title: <FormattedMessage id="TopicStatistics.giftQuiz.result" />,
    hideInSearch: true,
    render: (_n, record) =>
      typeof record.summary === 'number' ? `${(record.summary * 100).toFixed(2)}%` : '-',
  },
];

const getQuestionColumns = (giftQuizTopicStat: undefined | API.GiftQuizTopicStat): TableColumn[] =>
  giftQuizTopicStat !== undefined
    ? Object.keys(giftQuizTopicStat)
        .filter((k) => k.includes('question'))
        .map<TableColumn>((key, i) => ({
          title: <FormattedMessage id="TopicStatistics.giftQuiz.question" values={{ i: i + 1 }} />,
          dataIndex: key,
          hideInSearch: true,
        }))
    : [];

interface Props {
  quizTopics: API.TopicQuiz[];
}

export const GiftQuizStatistics: React.FC<Props> = ({ quizTopics }) => {
  const [selectedTopicId, setSelectedTopicId] = useState(quizTopics?.[0]?.id);
  const [dynamicColumns, setDynamicColumns] = useState<TableColumn[]>([]);

  const topicOptions = useMemo(
    () =>
      quizTopics.reduce<{ value: number; label: string }[]>(
        (acc, { id, title }) =>
          title !== undefined && id !== undefined ? [...acc, { value: id, label: title }] : acc,
        [],
      ),
    [quizTopics],
  );

  const columns: TableColumn[] = useMemo(
    () => [
      {
        dataIndex: 'topic_id',
        title: <FormattedMessage id="TopicStatistics.giftQuiz.topic" />,
        hideInTable: true,
        valueType: 'select',
        fieldProps: {
          options: topicOptions,
          defaultValue: topicOptions?.[0]?.value,
          allowClear: false,
        },
      },
      ...staticColumns,
      ...dynamicColumns,
    ],
    [topicOptions, dynamicColumns],
  );

  const handleRequest = useCallback(async ({ topic_id = selectedTopicId }: TableParams) => {
    if (!topic_id) return { success: false, data: [], total: 0 };
    const response = await getTopicStats(topic_id, TopicStatsKey.QuizSummary);

    // API.TopicStats is keyed by the stat's class name (the TopicStatsKey value).
    const stats = response.success
      ? (response.data as unknown as Partial<Record<TopicStatsKey, API.GiftQuizTopicStat[]>>)
      : undefined;
    const rows = stats?.[TopicStatsKey.QuizSummary];
    if (!rows) {
      return { success: false, data: [], total: 0 };
    }

    const [responseColumns, ...data] = rows;
    setDynamicColumns(getQuestionColumns(responseColumns));

    return {
      success: true,
      data,
      total: data.length,
    };
  }, []);

  return (
    <ProTable<API.GiftQuizTopicStat, TableParams>
      headerTitle={<FormattedMessage id="TopicStatistics.giftQuiz.title" />}
      onSubmit={({ topic_id }) => setSelectedTopicId(topic_id)}
      onReset={() => setSelectedTopicId(quizTopics?.[0]?.id)}
      toolBarRender={() => [
        <ExportTopicStatsButton
          key="export"
          stat={TopicStatsKey.QuizSummary}
          topic_id={selectedTopicId}
        />,
      ]}
      className="table-standalone"
      request={handleRequest}
      columns={columns}
      search={{ layout: 'vertical' }}
      scroll={{ x: 1500 }}
    />
  );
};
