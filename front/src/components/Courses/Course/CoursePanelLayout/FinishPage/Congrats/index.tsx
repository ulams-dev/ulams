import { useTranslation } from "react-i18next";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { IconCongrats } from "@/icons/index";
import styles from "../styles.module.css";

interface Props {
  onNextClick: () => void;
}

export const CoursePanelFinishPageCongrats = ({ onNextClick }: Props) => {
  const { t } = useTranslation();

  return (
    <div className={styles.centeredWrapper}>
      <div className={styles.iconContainer}>
        <IconCongrats />
      </div>
      <Title className={styles.title} level={1}>
        {t("CoursePanel.FinishPage.Congrats")}
      </Title>
      <Title className={styles.subtitle} level={2}>
        {t("CoursePanel.FinishPage.Subtitle")}
      </Title>
      <Text className={styles.text}>{t("CoursePanel.FinishPage.Text")}</Text>
      <Button className={styles.button} onClick={onNextClick}>
        {t("Next")}
      </Button>
    </div>
  );
};
