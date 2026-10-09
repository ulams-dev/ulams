import { Tag } from 'antd';
import React from 'react';
import { FormattedMessage } from 'umi';

import type { AdaptStatus } from '@/services/ulams/adapt';

const COLORS: Record<AdaptStatus, string> = {
  draft: 'default',
  building: 'processing',
  built: 'success',
  failed: 'error',
};

export const AdaptStatusTag: React.FC<{ status: AdaptStatus }> = ({ status }) => (
  <Tag color={COLORS[status] ?? 'default'}>
    <FormattedMessage id={`adapt.status.${status}`} defaultMessage={status} />
  </Tag>
);

export const errorText = (error: any): string => {
  const first = Object.values(error?.data?.errors ?? {})
    .flat()
    .slice(0, 5)
    .join('\n');
  return first || error?.data?.message || error?.message || String(error);
};
