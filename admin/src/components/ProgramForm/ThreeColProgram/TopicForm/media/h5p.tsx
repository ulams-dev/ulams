import H5PContentSelect from '@/components/H5PContentSelect';
import { Button, Col, Divider, Row, Typography, message } from 'antd';
import React, { useState } from 'react';

import UploadH5P from '@/components/H5P/upload';
import { FormattedMessage, useIntl } from 'umi';

import H5PCard from '@/components/H5P/card';

export const H5PForm: React.FC<{
  id: string;
  onChange: (value: string) => void;
}> = ({ id, onChange }) => {
  const intl = useIntl();
  const [editId, setEditId] = useState<string>(
    id === 'new' || id === undefined || id === null || id === '' ? 'new' : String(id),
  );

  return (
    <React.Fragment>
      <Row gutter={16} align="middle" className="create-new">
        <Col>
          <Button type="primary" onClick={() => setEditId('new')}>
            {' '}
            <FormattedMessage id="create_new" />
          </Button>
        </Col>

        <Col span={4}>
          <Typography>
            <small>
              <FormattedMessage id="or" />
            </small>{' '}
            <FormattedMessage id="reuse_existing" />
          </Typography>
        </Col>

        <Col span={12}>
          <H5PContentSelect
            value={id}
            onChange={(value) => {
              onChange(value);
              setEditId(value ? String(value) : 'new');
            }}
          />
        </Col>
      </Row>

      <Row
        style={{
          marginTop: '30px',
        }}
      >
        <Col span={24}>
          <H5PCard
            key={editId}
            id={editId}
            onSubmit={(h5pId) => {
              onChange(String(h5pId));
              setEditId(String(h5pId));
            }}
          />
        </Col>
      </Row>

      <Divider />

      <Row gutter={16}>
        <Col span={24}>
          <Row>
            <UploadH5P
              onSuccess={(response) => {
                if (response.success && response.data.contentId) {
                  onChange(String(response.data.contentId));
                  setEditId(String(response.data.contentId));
                }
              }}
              onError={(errorMessage) =>
                message.error(
                  errorMessage ||
                    intl.formatMessage({
                      id: 'error',
                    }),
                )
              }
            />
          </Row>
        </Col>
      </Row>
    </React.Fragment>
  );
};

export default H5PForm;
