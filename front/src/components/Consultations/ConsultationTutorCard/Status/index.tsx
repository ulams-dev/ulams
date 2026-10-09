import { useTranslation } from "react-i18next";
import { API } from "@ulams/sdk";
import Status, { StatusTypes } from "@/components/Common/Status";
import ConsultationTutorCardButtons from "../Actions";
import styles from "./styles.module.css";

interface Props {
  consultation: API.AppointmentTerm;
}

const ConsultationTutorCardStatus = ({ consultation }: Props) => {
  const { status, is_ended } = consultation;
  const { t } = useTranslation();
  const isReported = status === "reported";
  const isApproved = status === "approved";
  const isRejected = status === "reject";

  if (isRejected) {
    return (
      <Status
        status={StatusTypes.CANCELED}
        name={t("ConsultationStatus.Canceled")}
      />
    );
  }
  if (is_ended) {
    return (
      <Status status={StatusTypes.ENDED} name={t("ConsultationStatus.Ended")} />
    );
  }
  if (isReported) {
    return (
      <div className={styles.root}>
        <Status
          status={StatusTypes.WAITING}
          name={t("ConsultationStatus.Unconfirmed")}
        />

        <ConsultationTutorCardButtons consultation={consultation} />
      </div>
    );
  }
  if (isApproved) {
    return (
      <Status
        status={StatusTypes.ACCEPTED}
        name={t("ConsultationStatus.Appointment")}
      />
    );
  }
  return <Status status={StatusTypes.DEFAULT} name={status} />;
};

export default ConsultationTutorCardStatus;

// ConsultationStatus: {
//   Unconfirmed: "Unconfirmed",
//   Bought: "Bought",
//   Canceled: "Canceled",
//   Appointment: "Appointment",
// }
