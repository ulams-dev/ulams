import Layout from "@/components/_App/Layout";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { useTranslation } from "react-i18next";
import { useHistory } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import styles from "./styles.module.css";

interface AlternativeButton {
  goTo: string;
  goToText: string;
}

interface Props {
  error: string;
  goTo?: string;
  goToText?: string;
  alternativeButton?: AlternativeButton;
}

const ErrorBox: React.FC<Props> = ({
  error,
  goTo = routeRoutes.courses,
  goToText,
  alternativeButton,
}) => {
  const { t } = useTranslation();
  const history = useHistory();
  const buttonText = goToText ?? t("CoursePage.SeeOtherCourses");

  return (
    <Layout>
      <div className={styles.errorPage}>
        <Text size="16">
          <strong>{t("CoursePage.ErrorOccurred")}</strong>
        </Text>
        <Text size="14">{error}</Text>
        <hr />
        <div className={styles.buttonsBlock}>
          <Button mode="secondary" onClick={() => history.push(goTo)}>
            {buttonText}
          </Button>
          {alternativeButton && (
            <Button
              mode="outline"
              onClick={() => history.push(alternativeButton.goTo)}
            >
              {alternativeButton.goToText}
            </Button>
          )}
        </div>
      </div>
    </Layout>
  );
};
export default ErrorBox;
