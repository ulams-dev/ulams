import React, { useContext, useEffect } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import Layout from "../../components/_App/Layout";
import { API } from "@ulams/sdk";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { useTranslation } from "react-i18next";

import { useThemeTokens } from "@ulams/components/theme/applyTheme";
import styles from "./TutorsPage.module.css";
import { Spin } from "@ulams/components/components/atoms/Spin/Spin";
import { CourseCard } from "@ulams/components/components/molecules/CourseCard/CourseCard";
import Image from "@ulams/sdk/react/components/Image";
import { Link } from "react-router-dom";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import Breadcrumbs from "@/components/Common/Breadcrumbs";
import { Col, Row } from "react-grid-system";
import Container from "@/components/Common/Container";
import { APP_CONFIG } from "@/config/app";
import routeRoutes from "@/components/Routes/routes";

const TutorsPage = () => {
  const { tutors, fetchTutors } = useContext(UlamsContext);

  const { t } = useTranslation();
  const theme = useThemeTokens();

  useEffect(() => {
    fetchTutors();
  }, [fetchTutors]);

  return (
    <Layout>
      <div className="advisor-area">
        <Container>
          <Breadcrumbs
            items={[
              <Link to={routeRoutes.home}>{t<string>("Home")}</Link>,
              <Text size="12">{t("Tutors")}</Text>,
            ]}
          />
          <div className={styles.titleWrapper}>
            <Title level={1}> {t("Tutors")}</Title>
          </div>

          <Row>
            {tutors.loading && (
              <div
                style={{
                  display: "flex",
                  justifyContent: "center",
                  width: "100%",
                  minHeight: "500px",
                  flexDirection: "column",
                  alignItems: "center",
                }}
                className="loader-wrapper"
              >
                <Spin color={theme?.primaryColor} />
              </div>
            )}
            {!tutors.loading &&
              (tutors.list || []).map((tutor: API.UserItem) => (
                <Col sm={6} md={6} lg={4} key={tutor.id}>
                  <CourseCard
                    id={Number(tutor.id)}
                    title={tutor.name}
                    image={
                      <Link to={`/tutors/${tutor.id}`}>
                        {tutor.path_avatar ? (
                          <Image
                            path={tutor.path_avatar}
                            srcSizes={[380, 380 * 2]}
                          />
                        ) : (
                          <img
                            className="tutor-card__avatar"
                            src={APP_CONFIG.tutorPlaceholderPath}
                            alt="tutor_avatar"
                          />
                        )}
                      </Link>
                    }
                    subtitle={
                      <Link to={`/tutors/${tutor.id}`}>
                        <Text size="16">
                          <strong>
                            {tutor.first_name} {tutor.last_name}
                          </strong>
                        </Text>
                      </Link>
                    }
                  />
                </Col>
              ))}
          </Row>
        </Container>
      </div>
    </Layout>
  );
};

export default TutorsPage;
