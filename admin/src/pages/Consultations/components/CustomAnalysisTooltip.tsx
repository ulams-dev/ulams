import type { ChartPoint } from '@/pages/Consultations/components/types';
import { EMOTION_POOL, formatTime, getLabelColorByValue } from '@/utils/utils';
import { FormattedMessage } from '@@/exports';
import styles from './CustomAnalysisTooltip.module.css';

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
    <div className={styles.wrapper}>
      <div className={styles.header}>
        <span className={styles.title}>
          {emotion.icon} <FormattedMessage id={emotion.labelId} />
        </span>
        <span
          className={styles.badge}
          style={{
            color: valColor,
            background: `${valColor}15`,
            border: `1px solid ${valColor}40`,
          }}
        >
          {data.attention}%
        </span>
      </div>

      <div className={styles.timeRange}>
        <FormattedMessage id="time_segment" />: <b>{timeRange}</b>
      </div>

      {data.screen_path && (
        <img className={styles.preview} src={data.screen_path as string} alt="preview" />
      )}
    </div>
  );
};
