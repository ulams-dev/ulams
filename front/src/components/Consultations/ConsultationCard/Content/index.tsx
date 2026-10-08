import { API } from "@ulams/sdk";
import { addTimeToDate, extractTimeUnits } from "@/utils/date";
import DateInfo, { DateInfoTypes } from "@/components/Common/DateInfo";
import styles from "./styles.module.css";

interface Props {
  consultation: API.Consultation;
}

const ConsultationCardContent = ({ consultation }: Props) => {
  const isEnded = consultation.is_ended;
  const isReported = consultation.executed_status === "reported";
  const isApproved = consultation.executed_status === "approved";
  const isNotReported = consultation.executed_status === "not_reported";

  return (
    <div className={styles.root}>
      {isEnded && consultation.executed_at && (
        <DateInfo
          type={DateInfoTypes.ENDED}
          date={addTimeToDate(
            consultation.executed_at,
            extractTimeUnits(`${consultation.duration}`)
          )}
        />
      )}
      {isReported && !isEnded && (
        <DateInfo
          type={DateInfoTypes.WAITING}
          date={consultation.executed_at}
        />
      )}
      {isApproved && !isEnded && (
        <DateInfo
          type={DateInfoTypes.ACCEPTED}
          date={consultation.executed_at}
        />
      )}
      {isNotReported && <DateInfo type={DateInfoTypes.DEFAULT} />}
    </div>
  );
};

export default ConsultationCardContent;
