import { API } from "@ulams/sdk";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { IconCircleError, IconSuccess } from "@/icons/index";
import IconText from "@ulams/components/components/atoms/IconText/IconText";
import { useContext } from "react";
import { UlamsContext } from "@ulams/sdk/react";
import { useTranslation } from "react-i18next";
import Status, { StatusTypes } from "@/components/Common/Status";
import styles from "./styles.module.css";

interface Props {
  consultation: API.AppointmentTerm;
}

const statuses = {
  reported: {
    type: StatusTypes.WAITING,
    info: "ConsultationStatus.UnconfirmedInfo",
  },
  approved: {
    type: StatusTypes.ACCEPTED,
    info: "ConsultationStatus.ConfirmedInfo",
  },
  reject: {
    type: StatusTypes.ENDED,
    info: "ConsultationStatus.RejectedInfo",
  },
};
type StatusKey = keyof typeof statuses;

const ConsultationTutorCardContentUserInfo = ({ consultation }: Props) => {
  const { approveConsultationTerm, rejectConsultationTerm } =
    useContext(UlamsContext);
  const { t } = useTranslation();

  const renderStatus = (status: StatusKey) => {
    switch (status) {
      case "reported":
        return (
          <Status
            status={StatusTypes.WAITING}
            name={t("ConsultationStatus.Unconfirmed")}
          />
        );
      case "approved":
        return (
          <Status
            status={StatusTypes.ACCEPTED}
            name={t("ConsultationStatus.Appointment")}
          />
        );
      case "reject":
        return (
          <Status
            status={StatusTypes.ENDED}
            name={t("ConsultationStatus.Canceled")}
          />
        );
    }
  };

  return (
    <div className={styles.root}>
      {consultation.users.map((user) => (
        <div key={user.id}>
          <Text className={styles.text}>
            {user.first_name} {user.last_name}
          </Text>
          <Text className={styles.text}>{user.email}</Text>
          {consultation.users.length > 1 && (
            <>
              {statuses[user.executed_status as StatusKey] &&
                renderStatus(user.executed_status as StatusKey)}
              {
                <div className={styles.buttonWrapper}>
                  <IconText
                    icon={<IconSuccess />}
                    text={t("Confirm")}
                    onClick={() =>
                      approveConsultationTerm(
                        consultation?.consultation_term_id,
                        consultation.date,
                        user.id
                      )
                    }
                  />
                  <IconText
                    icon={<IconCircleError />}
                    text={t("Cancel")}
                    onClick={() =>
                      rejectConsultationTerm(
                        consultation?.consultation_term_id,
                        consultation.date,
                        user.id
                      )
                    }
                  />
                </div>
              }
            </>
          )}

          <hr />
        </div>
      ))}
    </div>
  );
};

export default ConsultationTutorCardContentUserInfo;
