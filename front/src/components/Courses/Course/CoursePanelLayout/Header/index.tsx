import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";
import { useCoursePanel } from "@/components/Courses/Course/Context";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import routeRoutes from "@/components/Routes/routes";
import { IconCircleClose } from "@/icons/index";
import styles from "./styles.module.css";

export const CoursePanelHeader = () => {
  const { currentCourseProgram } = useCoursePanel();
  const { t } = useTranslation();

  return (
    <header className={styles.wrapper}>
      <Title className={styles.title}>
        <span>{t("CoursePanel.Course", { defaultValue: "Kurs" })}</span>{" "}
        {currentCourseProgram?.title}
      </Title>
      <Link to={routeRoutes.myProfile}>
        <div className={styles.container}>
          {!isMobile && (
            <Text className={styles.iconText}>{t("CoursePanel.Leave")}</Text>
          )}
          <IconCircleClose />
        </div>
      </Link>
    </header>
  );
};
