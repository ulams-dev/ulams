import { Link, useParams } from "react-router-dom";
import React, { useContext, useEffect } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import Preloader from "@/components/_App/Preloader";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import Breadcrumbs from "@/components/Common/Breadcrumbs";
import { Col, Row } from "react-grid-system";
import { useTranslation } from "react-i18next";
import ConsultationHero from "@/components/Consultations/Consultation/ConsultationHero";
import ConsultationSidebar from "@/components/Consultations/Consultation/ConsultationSidebar";
import { MarkdownRenderer } from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { fixContentForMarkdown } from "@ulams/components/utils/components/markdown";
import ConsultationsSlider from "@/components/Consultations/ConsultationsSlider";
import Layout from "@/components/_App/Layout";
import Container from "../../Common/Container";
import routeRoutes from "@/components/Routes/routes";
import SidebarSkeleton from "@/components/Skeletons/CoursePage/sidebar";
import { StyledCoursePage } from "@/pages/courses/course/styles";
import ConsultationPageContentSkeleton from "@/components/Skeletons/Consultation";
import { CourseAuthor } from "@/pages/courses/course/Components";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import styles from "./styles.module.css";

const Consultation = () => {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const { consultation, fetchConsultation, consultations } =
    useContext(UlamsContext);

  const consultationCategories = consultation.value?.categories?.map(
    (category: Ulams.Categories.Models.Category) => category.name
  );

  useEffect(() => {
    if (id) {
      fetchConsultation(Number(id));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  if (consultation.loading && !consultation.value?.id) {
    return <Preloader />;
  }

  if (consultation.error) {
    return <pre>{consultation.error.message}</pre>;
  }

  return (
    <Layout metaTitle={`${t("Consultation")} ${consultation.value?.name}`}>
      {consultation.loading && (
        <>
          <StyledCoursePage>
            <Container>
              <Row>
                <Col md={12} lg={8}>
                  <ConsultationPageContentSkeleton />
                </Col>
                <Col md={12} lg={3} offset={{ lg: 1 }}>
                  <SidebarSkeleton />
                </Col>
              </Row>
            </Container>
          </StyledCoursePage>
        </>
      )}
      {!consultation.loading && (
        <StyledCoursePage>
          <Container>
            <Row>
              <Col xs={12}>
                <Breadcrumbs
                  items={[
                    <Link to={routeRoutes.home}>{t("Home")}</Link>,
                    <Link to={routeRoutes.consultations}>
                      {t("Consultations")}
                    </Link>,
                    <Text size="12">{consultation?.value?.name}</Text>,
                  ]}
                />
              </Col>
              <Col xs={12} md={9}>
                <Row>
                  <Col md={12}>
                    <ConsultationHero consultation={consultation.value} />
                  </Col>
                  <Col md={12}>
                    {consultation?.value?.description &&
                      fixContentForMarkdown(consultation.value.description) !==
                        "" && (
                        <div className={styles.description}>
                          <MarkdownRenderer>
                            {consultation.value.description}
                          </MarkdownRenderer>
                        </div>
                      )}
                  </Col>

                  <Col md={12}>
                    {" "}
                    <br />
                    <Title as="h3" level={4} className="title">
                      {t<string>("ConsultationPage.Teacher")}
                    </Title>
                    {consultation.value &&
                      // @ts-ignore TODO: add to sdk
                      consultation.value.teachers.map((author) => (
                        <CourseAuthor author={author} />
                      ))}
                  </Col>
                </Row>
              </Col>

              {consultation?.value?.product && (
                <Col xs={12} md={3}>
                  <ConsultationSidebar consultation={consultation.value} />
                </Col>
              )}
            </Row>
          </Container>
        </StyledCoursePage>
      )}
      {consultationCategories && consultationCategories.length > 0 && (
        <section className={styles.relatedConsultations}>
          <Container>
            {consultationCategories.map((category) => (
              <ConsultationsSlider
                key={category}
                category={category}
                title={`${t("Inni specjaliści")} ${category}`}
                consultations={
                  consultations?.list?.data?.filter(
                    (consultation) => consultation.id !== Number(id)
                  ) || []
                }
              />
            ))}
          </Container>
        </section>
      )}
    </Layout>
  );
};

export default Consultation;
