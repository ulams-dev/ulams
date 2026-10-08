import { Col, Spin, Typography } from 'antd';
import React, { useCallback } from 'react';
import {
  Area,
  AreaChart,
  CartesianGrid,
  Tooltip as RechartsTooltip,
  ResponsiveContainer,
  XAxis,
  YAxis,
} from 'recharts';
import styled from 'styled-components';
import { FormattedMessage } from 'umi';

import { CustomAnalysisTooltip } from '@/pages/Consultations/components/CustomAnalysisTooltip';
import type { ChartPoint } from '@/pages/Consultations/components/types';
import { ANALYSIS_COLORS, getLabelColorByValue } from '@/utils/utils';
import EmotionDot from './EmotionDot';

const { Text } = Typography;

const ScrollContainer = styled.div`
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  background: transparent;
  touch-action: pan-x;
  &::-webkit-scrollbar {
    height: 8px;
  }
  &::-webkit-scrollbar-thumb {
    background: #e8e8e8;
    border-radius: 4px;
  }
`;

const ChartWrapper = styled.div<{ $width: string }>`
  width: ${(props) => props.$width};
  min-width: 100%;
  display: flex;
  flex-direction: column;
  padding: 20px 0 100px 0;
`;

const ChartHeader = styled.div<{ $paddingLeft: number }>`
  margin-bottom: 16px;
  padding-left: ${(props) => props.$paddingLeft}px;
`;

const ChartContainer = styled.div<{ $height: number }>`
  height: ${(props) => props.$height}px;
  width: 100%;
  position: relative;
`;

const SectionTitle = styled(Text)`
  font-size: 16px;
`;

const Y_AXIS_WIDTH = 45;

interface AnalysisChartsProps {
  chartData: ChartPoint[];
  loading: boolean;
  chartWidth: string;
  resolution: number;
}

const AnalysisCharts: React.FC<AnalysisChartsProps> = ({
  chartData,
  loading,
  chartWidth,
  resolution,
}) => {
  const renderDynamicGradient = useCallback(
    (gradientId: string, isArea: boolean) => {
      if (chartData.length === 0) return null;
      return (
        <linearGradient id={gradientId} x1="0" y1="0" x2="1" y2="0">
          {chartData.map((point, index) => (
            <stop
              key={`${gradientId}-${point.time}`}
              offset={`${(index / (chartData.length - 1)) * 100}%`}
              stopColor={getLabelColorByValue(point.attention)}
              stopOpacity={isArea ? 0.4 : 1}
            />
          ))}
        </linearGradient>
      );
    },
    [chartData],
  );

  return (
    <Col span={24}>
      <Spin spinning={loading}>
        <ScrollContainer>
          <ChartWrapper $width={chartWidth}>
            <ChartHeader $paddingLeft={Y_AXIS_WIDTH}>
              <SectionTitle strong>
                <FormattedMessage id="listeners_engagement" />
              </SectionTitle>
            </ChartHeader>
            <ChartContainer $height={380}>
              <ResponsiveContainer>
                <AreaChart data={chartData} margin={{ top: 10, right: 30, left: 0, bottom: 20 }}>
                  <defs>
                    {renderDynamicGradient('dynamicFill', true)}
                    {renderDynamicGradient('dynamicStroke', false)}
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                  <XAxis
                    dataKey="time"
                    axisLine={{ stroke: ANALYSIS_COLORS.border }}
                    tick={{ fontSize: 12 }}
                    dy={10}
                    interval={resolution >= 300 ? 0 : 'preserveStartEnd'}
                  />
                  <YAxis
                    width={Y_AXIS_WIDTH}
                    domain={[0, 100]}
                    ticks={[0, 50, 100]}
                    tickFormatter={(v: number) => `${v}%`}
                    axisLine={false}
                    tickLine={false}
                    tick={{ fontSize: 11 }}
                  />
                  <RechartsTooltip
                    content={<CustomAnalysisTooltip />}
                    cursor={{ stroke: '#40a9ff', strokeWidth: 1.5 }}
                  />
                  <Area
                    type="monotone"
                    dataKey="attention"
                    stroke="url(#dynamicStroke)"
                    strokeWidth={3}
                    fill="url(#dynamicFill)"
                    isAnimationActive={false}
                  />
                </AreaChart>
              </ResponsiveContainer>
            </ChartContainer>

            <ChartHeader $paddingLeft={Y_AXIS_WIDTH} style={{ marginTop: 60 }}>
              <SectionTitle strong>
                <FormattedMessage id="detected_emotions" />
              </SectionTitle>
            </ChartHeader>
            <ChartContainer $height={250}>
              <ResponsiveContainer>
                <AreaChart data={chartData} margin={{ top: 0, right: 30, left: 0, bottom: 100 }}>
                  <XAxis
                    dataKey="time"
                    axisLine={{ stroke: ANALYSIS_COLORS.border }}
                    tickLine={false}
                    tick={{ fontSize: 12 }}
                    dy={10}
                    interval={resolution >= 300 ? 0 : 'preserveStartEnd'}
                  />
                  <YAxis
                    width={Y_AXIS_WIDTH}
                    axisLine={false}
                    tick={false}
                    tickLine={false}
                    domain={[0, 100]}
                  />
                  <RechartsTooltip content={<CustomAnalysisTooltip />} />
                  <Area
                    type="monotone"
                    dataKey="max_emotion_percentage_val"
                    stroke="none"
                    fill="none"
                    isAnimationActive={false}
                    dot={<EmotionDot />}
                  />
                </AreaChart>
              </ResponsiveContainer>
            </ChartContainer>
          </ChartWrapper>
        </ScrollContainer>
      </Spin>
    </Col>
  );
};

export default AnalysisCharts;
