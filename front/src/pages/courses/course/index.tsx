import { useCallback, useContext, useEffect, useState } from "react";
import CoursesDetailsSidebar from "@/components/Courses/SingleCoursesTwo/CoursesDetailsSidebar/index";
import { Link, useHistory, useParams } from "react-router-dom";
import { UlamsContext } from "@ulams/sdk/react/context";
import { useTranslation } from "react-i18next";
import Layout from "@/components/_App/Layout";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { CourseProgram } from "@ulams/components/components/organisms/CourseProgram/CourseProgram";
import { MarkdownRenderer } from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import CourseProgramPreview from "@/components/Courses/Course/CourseProgramPreview";
import { API } from "@ulams/sdk";
import { Modal } from "@ulams/components/components/atoms/Modal/Modal";
import Breadcrumbs from "@/components/Common/Breadcrumbs";
import { fixContentForMarkdown } from "@ulams/components/utils/components/markdown";
import { Col, Row } from "react-grid-system";
import Container from "@/components/Common/Container";
import { ModalCourseAccess } from "@ulams/components/components/organisms/ModalCourseAccess";
import { QuestionnaireModelType } from "@/types/questionnaire";
import { ModalOverwriteGlobal, StyledCoursePage, styles } from "./styles";
import {
  CourseMainInfo,
  CourseAuthor,
  CourseRatings,
  CourseRelated,
} from "./Components";
import routeRoutes from "@/components/Routes/routes";
import { isMobile } from "react-device-detect";
import { ArrowRight } from "@/icons/index";
import SidebarSkeleton from "@/components/Skeletons/CoursePage/sidebar";
import CoursePageContentSkeleton from "@/components/Skeletons/CoursePage/content";
import { API_URL } from "@/config/index";
import { toast } from "@/utils/toast";

const CoursePage = () => {
  const [questionnaires, setQuestionnaires] = useState<API.Questionnaire[]>([]);
  const [courseAccessModalVisible, setCourseAccessModalVisible] =
    useState(false);

  const [previewTopic, setPreviewTopic] = useState<API.Topic>();
  const { id } = useParams<{ id: string }>();
  const { t } = useTranslation();
  const history = useHistory();
  const {
    course,
    fetchCourse,
    fetchCourses,
    fetchCourseAccess,
    fetchQuestionnaires,
  } = useContext(UlamsContext);

  const closeCourseAccessModal = useCallback(
    () => setCourseAccessModalVisible(false),
    []
  );

  const openCourseAccessModal = useCallback(
    () => setCourseAccessModalVisible(true),
    []
  );

  const refreshCurrentCourseAccess = useCallback(
    () =>
      fetchCourseAccess({
        course_id: Number(id),
        current_page: 1,
        per_page: 1,
      }),
    [id, fetchCourseAccess]
  );

  useEffect(() => {
    fetchCourses({ per_page: 6 });
    if (id) {
      fetchCourse(Number(id));
      refreshCurrentCourseAccess();
      fetchQuestionnaires(QuestionnaireModelType.COURSE, Number(id)).then(
        (response) => response.success && setQuestionnaires(response.data)
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const getPreviewContent = useCallback(
    async (topic: API.Topic) => {
      try {
        const request = await fetch(
          `${API_URL}/api/courses/${course.value?.id}/preview/${topic.id}`
        );

        if (request.ok) {
          const response = await request.json();

          setPreviewTopic(response.data);
        }
      } catch (error) {
        console.error(error);
        toast(`${t("UnexpectedError")}`, "error");
      }
    },
    [course, t]
  );

  if (course.error) {
    return <pre>{course.error.message}</pre>;
  }

  return (
    <Layout metaTitle={course?.value?.title || "Loading"}>
      {course.loading && (
        <>
          <StyledCoursePage>
            <Container>
              <Row>
                <Col md={12} lg={8}>
                  <CoursePageContentSkeleton />
                </Col>
                <Col md={12} lg={3} offset={{ lg: 1 }}>
                  <SidebarSkeleton />
                </Col>
              </Row>
            </Container>
          </StyledCoursePage>
        </>
      )}

      {!course.loading && course.value && (
        <>
          <StyledCoursePage>
            <Container>
              {!isMobile && (
                <Breadcrumbs
                  items={[
                    <Link to={routeRoutes.home}>{t("Home")}</Link>,
                    <Link to={routeRoutes.courses}>{t("Courses")}</Link>,
                    <Text size="13">{course.value.title}</Text>,
                  ]}
                />
              )}

              <Row>
                <Col md={12} lg={8}>
                  {isMobile && (
                    <button
                      className={styles.backButton}
                      onClick={() => history.push(routeRoutes.courses)}
                    >
                      <ArrowRight color="currentColor" />
                    </button>
                  )}
                  <CourseMainInfo courseData={course.value} />
                  {isMobile && course.value && (
                    <CoursesDetailsSidebar
                      course={course.value}
                      onRequestAccess={openCourseAccessModal}
                    />
                  )}
                  {course.value.description &&
                    fixContentForMarkdown(course.value.description) !== "" && (
                      <section className="course-description-short">
                        <MarkdownRenderer>
                          {course.value.description}
                        </MarkdownRenderer>
                      </section>
                    )}
                  {course.value.summary &&
                    fixContentForMarkdown(course.value.summary) !== "" && (
                      <section className="course-description">
                        <MarkdownRenderer>
                          {course.value.summary}
                        </MarkdownRenderer>
                      </section>
                    )}
                  <section className="">
                    <Title as="h3" level={4} className="title">
                      {t<string>("CoursePage.Teacher")}
                    </Title>
                    <Row>
                      {course.value &&
                        course.value.authors.map((author) => (
                          <CourseAuthor author={author} />
                        ))}
                    </Row>
                  </section>
                  {course.value.lessons && course.value.lessons.length > 0 && (
                    <CourseProgram
                      lessons={course.value.lessons}
                      onTopicClick={(topic) => getPreviewContent(topic)}
                    />
                  )}
                  <CourseRatings questionnaires={questionnaires} />
                </Col>

                {!isMobile && (
                  <Col md={12} lg={3} offset={{ lg: 1 }}>
                    {course.value && (
                      <CoursesDetailsSidebar
                        course={course.value}
                        onRequestAccess={openCourseAccessModal}
                      />
                    )}
                  </Col>
                )}
              </Row>
            </Container>
            <CourseRelated
              relatedProducts={course.value.product?.related_products}
            />
          </StyledCoursePage>
          <Modal
            onClose={() => setPreviewTopic(undefined)}
            visible={!!previewTopic}
            animation="zoom"
            maskAnimation="fade"
            destroyOnClose={true}
            width={600}
            bodyStyle={{
              maxHeight: "80vh",
              overflow: "auto",
            }}
          >
            <ModalOverwriteGlobal />
            {previewTopic && <CourseProgramPreview topic={previewTopic} />}
          </Modal>
          <Modal
            onClose={closeCourseAccessModal}
            visible={courseAccessModalVisible}
            animation="zoom"
            maskAnimation="fade"
            destroyOnClose
            width={600}
          >
            <ModalOverwriteGlobal />
            <ModalCourseAccess
              course={course.value}
              onCancel={closeCourseAccessModal}
              onSuccess={() => {
                refreshCurrentCourseAccess();
                closeCourseAccessModal();
              }}
            />
          </Modal>
        </>
      )}
    </Layout>
  );
};

export default CoursePage;
