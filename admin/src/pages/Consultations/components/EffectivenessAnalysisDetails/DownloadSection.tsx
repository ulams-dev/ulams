import { ANALYSIS_COLORS, formatExpirationTime } from '@/utils/utils';
import { DownloadOutlined } from '@ant-design/icons';
import { Button, Space, Typography } from 'antd';
import React from 'react';
import styled from 'styled-components';
import { FormattedMessage } from 'umi';

const { Text } = Typography;

const StyledDownloadButton = styled(Button)`
  border-radius: 8px;
`;

const ExpiryText = styled(Text)`
  font-size: 13px;
  margin-left: 4px;
`;

const ExpiryTime = styled.b`
  color: ${ANALYSIS_COLORS.darkText};
`;

interface DownloadSectionProps {
  url?: string | null;
  expirationTime?: number | string | null;
}

const DownloadSection: React.FC<DownloadSectionProps> = ({ url, expirationTime }) => {
  if (!url) return null;

  return (
    <Space key="download-section" size="middle">
      <StyledDownloadButton
        type="primary"
        icon={<DownloadOutlined />}
        size="large"
        onClick={() => window.open(url, '_blank')}
      >
        <FormattedMessage id="download_recording" />
      </StyledDownloadButton>

      {expirationTime && (
        <ExpiryText type="secondary">
          <FormattedMessage
            id="recording_available_until"
            values={{
              time: <ExpiryTime>{formatExpirationTime(Number(expirationTime))}</ExpiryTime>,
            }}
          />
        </ExpiryText>
      )}
    </Space>
  );
};

export default DownloadSection;
