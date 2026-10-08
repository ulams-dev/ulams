import { useContext, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import { API } from "@ulams/sdk";
import IconText from "@ulams/components/components/atoms/IconText/IconText";
import { IconCircleError, IconMenuVertical, IconSuccess } from "@/icons/index";
import DropdownMenu from "@ulams/components/components/molecules/DropdownMenu/DropdownMenu";
import { Button } from "@ulams/components/components/atoms/Button/Button";

interface Props {
  consultation: API.AppointmentTerm;
}

const ConsultationTutorCardButtons = ({ consultation }: Props) => {
  const { approveConsultationTerm, rejectConsultationTerm } =
    useContext(UlamsContext);
  const { t } = useTranslation();

  const menuItems = useMemo(
    () => [
      {
        id: 1,
        content: (
          <IconText
            icon={<IconSuccess />}
            text={t("Confirm")}
            onClick={() =>
              approveConsultationTerm(
                consultation?.consultation_term_id,
                consultation.date
              )
            }
          />
        ),
      },
      {
        id: 2,
        content: (
          <IconText
            icon={<IconCircleError />}
            text={t("Cancel")}
            onClick={() =>
              rejectConsultationTerm(
                consultation?.consultation_term_id,
                consultation.date
              )
            }
          />
        ),
      },
    ],
    [
      approveConsultationTerm,
      consultation?.consultation_term_id,
      rejectConsultationTerm,
      t,
      consultation.date,
    ]
  );

  return (
    <DropdownMenu
      menuItems={menuItems}
      child={
        <Button mode="icon">
          <IconMenuVertical />
        </Button>
      }
    />
  );
};

export default ConsultationTutorCardButtons;
