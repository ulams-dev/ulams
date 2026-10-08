import type { PropsWithChildren } from 'react';
import React, { useState } from 'react';

import TypeButton from '@/components/TypeButton';
import TypeDrawer from '@/components/TypeDrawer';
import { Space } from 'antd';

export type PossibleType =
  | 'App\\Models\\User'
  | 'App\\Models\\Course'
  | 'App\\Models\\Webinar'
  | 'Ulams\\Core\\Models\\User'
  | 'Ulams\\Cart\\Models\\Order'
  | 'Ulams\\Cart\\Models\\Course'
  | 'Ulams\\Webinars\\Models\\Webinar'
  | 'Ulams\\Auth\\Models\\UserGroup'
  | 'Ulams\\Consultations\\Models\\Consultation'
  | 'Ulams\\TopicTypeGift\\Models\\GiftQuiz'
  | 'Ulams\\Vouchers\\Models\\Order'
  | 'Questionnaire'
  | 'Product'
  | 'Students'
  | 'Category';

export const TypeButtonDrawer: React.FC<
  PropsWithChildren<{
    type: PossibleType;
    type_id: number;
    text?: React.ReactNode;
  }>
> = ({ type, type_id, text, children }) => {
  const [currentRow, setCurrentRow] = useState<API.LinkedType>({ type: '', value: null });

  return (
    <React.Fragment>
      <Space direction="vertical">
        {children}
        <TypeButton
          type={type}
          type_id={type_id}
          onData={(data) => setCurrentRow(data)}
          text={text}
        />
      </Space>
      <TypeDrawer
        data={currentRow}
        visible={!!currentRow.type}
        onClose={() => setCurrentRow({ type: '', value: null })}
      />
    </React.Fragment>
  );
};

export default TypeButtonDrawer;
