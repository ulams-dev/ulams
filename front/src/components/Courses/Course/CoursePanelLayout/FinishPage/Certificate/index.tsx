import { useCallback } from "react";
import { useTranslation } from "react-i18next";
import { Link } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import Button from "@ulams/components/components/atoms/Button/Button";
import Title from "@ulams/components/components/atoms/Typography/Title";
import Text from "@ulams/components/components/atoms/Typography/Text";
import { IconCertificateBig } from "@/icons/index";
import { useCertificateDownload } from "@/hooks/useDownloadCertificate";
import { Certificate } from "@ulams/sdk/types";
import styles from "../styles.module.css";

interface Props {
  certificates: Certificate[];
}

export const CoursePanelFinishPageCertificate = ({ certificates }: Props) => {
  const { t } = useTranslation();
  const { downloadCertificate, loadingId } = useCertificateDownload();

  const onDownload = useCallback(() => {
    certificates.forEach(({ id, title }) => {
      downloadCertificate(id, title);
    });
  }, [certificates, downloadCertificate]);

  return (
    <div className={`${styles.centeredWrapper} ${styles.certificateContainer}`}>
      <div className={styles.iconContainer}>
        <IconCertificateBig />
      </div>
      <Title className={styles.subtitle} level={2}>
        {t("CoursePanel.FinishPage.YourCertificate")}
      </Title>
      <Button
        className={styles.button}
        onClick={onDownload}
        loading={loadingId !== -1}
        disabled={loadingId !== -1}
      >
        {t("DownloadCertificate")}
      </Button>
      <Text className={styles.text}>
        {t("CoursePanel.FinishPage.CertificateText")}
      </Text>
      <Link to={routeRoutes.home}>
        <Button mode="outline" className={styles.button}>
          {t("BackToHomePage")}
        </Button>
      </Link>
    </div>
  );
};
