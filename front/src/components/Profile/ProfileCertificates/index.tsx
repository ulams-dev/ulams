import React, { useContext, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import { API } from "@ulams/sdk";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { PdfIcon } from "../../../icons";
import { useCertificateDownload } from "@/hooks/useDownloadCertificate";
import { CertificateCard } from "@ulams/components";
import { Col, Row } from "react-grid-system";
import ContentLoader from "@/components/_App/ContentLoader";
import styles from "./styles.module.css";

type CertType = API.Certificate;

const ProfileCertificates: React.FC = () => {
  const { certificates, fetchCertificates } = useContext(UlamsContext);
  const { t } = useTranslation();

  const { downloadCertificate, loadingId } = useCertificateDownload();

  useEffect(() => {
    fetchCertificates();
  }, [fetchCertificates]);

  return (
    <>
      <section className={styles.list}>
        {certificates.list?.data.length === 0 && (
          <Text className={styles.emptyMessage}>
            <strong>{t("MyProfilePage.EmptyCertificates")}</strong>
          </Text>
        )}
        <Row>
          {certificates &&
            certificates?.list?.data &&
            certificates.list?.data.length > 0 &&
            certificates?.list?.data
              ?.filter((cert: CertType) => cert.title)
              .map((cert: CertType) => (
                <Col lg={4}>
                  <CertificateCard
                    uptitle={
                      <Text size="13">{t("CoursePage.CourseTitle")}</Text>
                    }
                    title={
                      <Title level={4} as="h3">
                        {cert.title}
                      </Title>
                    }
                    dateUptitle={
                      <Text size="13">{t("CoursePage.CertificateDate")}</Text>
                    }
                    date={
                      <Text noMargin size={"16"} bold>
                        {new Date(cert.created_at).toLocaleDateString("pl-PL")}
                      </Text>
                    }
                    actions={
                      <div className={styles.buttonsContainer}>
                        {loadingId === cert.id ? (
                          <ContentLoader width="15px" height="15px" />
                        ) : (
                          <button
                            className={styles.downloadBtn}
                            onClick={() =>
                              downloadCertificate(cert.id, cert.title)
                            }
                          >
                            <PdfIcon />{" "}
                            <Text bold size="13">
                              {t("CoursePage.DownloadCertificate")}
                            </Text>
                          </button>
                        )}
                      </div>
                    }
                  />
                </Col>
              ))}
        </Row>
      </section>
    </>
  );
};

export default ProfileCertificates;
