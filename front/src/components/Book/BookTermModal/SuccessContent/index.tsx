import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import { useTranslation } from "react-i18next";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { IconSuccess } from "../../../../icons";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import styles from "./SuccessContent.module.css";

interface SuccessContentProps {
  onClick: () => void;
}

const SuccessContent = ({ onClick }: SuccessContentProps) => {
  const { t } = useTranslation();
  const theme = useThemeTokens();

  return (
    <div className={styles.root}>
      <IconSuccess width="50px" height="50px" color={theme?.primaryColor} />
      <Text className="text">{t("ConsultationPage.successTermInfo")}</Text>
      <Button mode="secondary" onClick={onClick} block>
        {t("ConsultationPage.Understand")}
      </Button>
    </div>
  );
};

export default SuccessContent;
