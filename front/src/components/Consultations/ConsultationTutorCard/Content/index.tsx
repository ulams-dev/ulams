import { API } from "@ulams/sdk";
import ConsultationTutorCardContentUserInfo from "./UserInfo";
import ConsultationTutorCardContentDateInfo from "./DateInfo";
import TimeInfo from "@/components/Common/TimeInfo";
import styles from "./styles.module.css";

interface Props {
  consultation: API.AppointmentTerm;
}

const ConsultationTutorCardContent = ({ consultation }: Props) => {
  return (
    <div className={styles.root}>
      <ConsultationTutorCardContentUserInfo consultation={consultation} />
      <TimeInfo time={consultation.duration} />
      <ConsultationTutorCardContentDateInfo consultation={consultation} />
    </div>
  );
};

export default ConsultationTutorCardContent;
