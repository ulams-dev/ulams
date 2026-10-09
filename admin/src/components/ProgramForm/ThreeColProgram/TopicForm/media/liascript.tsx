import { Button, Select, Space, Typography } from 'antd';
import React, { useEffect, useState } from 'react';
import { FormattedMessage, Link } from 'umi';

import type { LiaScriptDocument } from '@/services/ulams/liascript';
import { liascriptDocuments } from '@/services/ulams/liascript';

/** LiaScript topic: pick a LiaScript course; its current version is played. */
export const LiaScriptTopicForm: React.FC<{
  value?: number | string;
  onChange: (value: number) => void;
}> = ({ value, onChange }) => {
  const [documents, setDocuments] = useState<LiaScriptDocument[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    liascriptDocuments({ current: 1, pageSize: 200 })
      .then((r) => setDocuments(r.data))
      .finally(() => setLoading(false));
  }, []);

  const id = value ? Number(value) : undefined;

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <label htmlFor="liascript-document">
        <FormattedMessage id="liascript.course" defaultMessage="LiaScript course" />
      </label>
      <Select
        id="liascript-document"
        showSearch
        optionFilterProp="label"
        loading={loading}
        style={{ width: '100%' }}
        value={id}
        onChange={(v) => onChange(Number(v))}
        options={documents.map((d) => ({
          value: d.id,
          label: `${d.title} (v${d.current_version})`,
        }))}
      />
      <Space>
        {id && (
          <Link to={`/courses/liascript/${id}`}>
            <Button size="small">
              <FormattedMessage id="liascript.open_editor" defaultMessage="Edit the course" />
            </Button>
          </Link>
        )}
        <Link to="/courses/liascript">
          <Button size="small" type="link">
            <FormattedMessage id="liascript.new" defaultMessage="New LiaScript course" />
          </Button>
        </Link>
      </Space>
      <Typography.Text type="secondary">
        <FormattedMessage
          id="liascript.topic_hint"
          defaultMessage="The lesson completes when the learner reaches the last section of the course."
        />
      </Typography.Text>
    </Space>
  );
};

export default LiaScriptTopicForm;
