import { allContent } from '@/services/ulams/h5p';
import { searchSubstring } from '@/utils/utils';
import { Select } from 'antd';
import React, { useEffect, useState } from 'react';
import { FormattedMessage } from 'umi';

export const H5PContentSelect: React.FC<{
  state?: {
    type: number;
  };
  multiple?: boolean;
  value?: string;
  onChange?: (value: string) => void;
}> = ({ value, onChange, multiple = false }) => {
  const [contents, setContents] = useState<API.H5PContentListItem[]>([]);
  const [loading, setLoading] = useState<boolean>(false);

  const fetchContents = () => {
    setLoading(true);
    return allContent()
      .then((response) => response.success && setContents(response.data))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchContents();
  }, []);

  useEffect(() => {
    // a content created or uploaded in the topic form is not in the list yet
    if (value && contents.length && !contents.find((c) => String(c.id) === String(value))) {
      fetchContents();
    }
  }, [value]);

  return (
    <Select
      loading={loading}
      style={{ width: '100%' }}
      value={value ? String(value) : undefined}
      onChange={onChange}
      mode={multiple ? 'multiple' : undefined}
      showSearch
      placeholder={<FormattedMessage id="H5P_select_content" />}
      filterOption={(input, option) => searchSubstring(String(option?.label ?? ''), input)}
      options={contents.map((content) => ({
        value: String(content.id),
        label: `${content.id} ${content.title} (${content.main_library || content.library})`,
      }))}
    />
  );
};

export default H5PContentSelect;
