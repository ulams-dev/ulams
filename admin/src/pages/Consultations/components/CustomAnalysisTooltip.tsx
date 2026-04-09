import type { ChartPoint } from '@/pages/Consultations/components/types';
import { ANALYSIS_COLORS, EMOTION_POOL, formatTime, getLabelColorByValue } from '@/utils/utils';
import { FormattedMessage } from '@@/exports';
import styled from 'styled-components';

const CustomTooltipWrapper = styled.div`
  background: white;
  border: 1px solid ${ANALYSIS_COLORS.border};
  padding: 16px;
  border-radius: 10px;
  width: 260px;
  z-index: 1000;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
`;

const TooltipHeader = styled.div`
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
`;

const EmotionTitle = styled.span`
  font-size: 16px;
  font-weight: 600;
`;

const PercentageBadge = styled.span<{ $color: string }>`
  padding: 2px 10px;
  font-weight: 700;
  border-radius: 4px;
  color: ${(props) => props.$color};
  background: ${(props) => props.$color}15;
  border: 1px solid ${(props) => props.$color}40;
`;

const TimeRangeWrapper = styled.div`
  font-size: 12px;
  color: ${ANALYSIS_COLORS.textSecondary};
  margin-bottom: 4px;

  b {
    color: ${ANALYSIS_COLORS.darkText};
  }
`;

const PreviewImg = styled.img`
  width: 100%;
  height: 120px;
  object-fit: cover;
  background: #262626;
  border-radius: 6px;
  margin-top: 8px;
`;

interface CustomAnalysisTooltipProps {
  active?: boolean;
  payload?: {
    payload: ChartPoint;
  }[];
  label?: string | number;
}

export const CustomAnalysisTooltip = ({ active, payload }: CustomAnalysisTooltipProps) => {
  if (!active || !payload || !payload.length) return null;

  const data = payload[0].payload;
  const emotion = EMOTION_POOL.find((e) => e.key === data.emotionKey) || EMOTION_POOL[6];
  const valColor = getLabelColorByValue(data.attention);
  const startTime = data.second;
  const interval = data.interval ? Number(data.interval) : 15;
  const endTime = startTime + interval;

  const timeRange = `${formatTime(startTime)} - ${formatTime(endTime)}`;

  return (
    <CustomTooltipWrapper>
      <TooltipHeader>
        <EmotionTitle>
          {emotion.icon} <FormattedMessage id={emotion.labelId} />
        </EmotionTitle>
        <PercentageBadge $color={valColor}>{data.attention}%</PercentageBadge>
      </TooltipHeader>

      <TimeRangeWrapper>
        <FormattedMessage id="time_segment" />: <b>{timeRange}</b>
      </TimeRangeWrapper>

      {data.screen_path && <PreviewImg src={data.screen_path as string} alt="preview" />}
    </CustomTooltipWrapper>
  );
};
