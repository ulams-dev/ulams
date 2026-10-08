import type { DotProps } from '@/pages/Consultations/components/types';
import { EMOTION_POOL, EmotionKey } from '@/utils/utils';
import styled from 'styled-components';

const EmotionIconWrapper = styled.div`
  font-size: 26px;
  text-align: center;
`;

const EmotionDot = ({ cx, payload }: DotProps) => {
  if (cx === undefined || !payload) {
    return null;
  }

  const emotion =
    EMOTION_POOL.find((e) => e.key === payload.emotionKey) ||
    EMOTION_POOL.find((e) => e.key === EmotionKey.NEUTRAL)!;

  return (
    <foreignObject key={`${cx}-${payload.time}`} x={cx - 15} y={30} width={30} height={40}>
      <EmotionIconWrapper title={payload.emotionKey}>{emotion.icon}</EmotionIconWrapper>
    </foreignObject>
  );
};

export default EmotionDot;
