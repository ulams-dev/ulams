import React from "react";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { useTranslation } from "react-i18next";
import { Link, useHistory } from "react-router-dom";
import { isMobile } from "react-device-detect";
import CourseImgPlaceholder from "../CourseImgPlaceholder";
import { ResponsiveImage } from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import { Row, Col } from "react-grid-system";
import Container from "../../Common/Container";
import CoursesSlider from "../CoursesSlider";
import routeRoutes from "@/components/Routes/routes";
import CategoriesBreadCrumbs from "@/components/Categories/CategoriesBreadCrumbs";
import { NewCourseCard } from "@ulams/components/components/molecules/NewCourseCard/NewCourseCard";
import useFetchCourses from "@/hooks/courses/useFetchCourses";
import { CourseCardSkeleton } from "@/components/Skeletons/CourseCard";
import styles from "./styles.module.css";

const PromotedCoursesSection: React.FC = () => {
  const { courses, loading } = useFetchCourses({
    per_page: 8,
  });

  const history = useHistory();
  const { t } = useTranslation();

  return (
    <section className={`${styles.section} ${isMobile ? styles.mobile : ""}`}>
      <Container className={styles.container}>
        <div className={styles.headerWrapper}>
          <Title level={1} as="h2">
            {t("Homepage.AwardedCoursesTitle")}
          </Title>
          <Button
            mode="outline"
            onClick={() => history.push(routeRoutes.courses)}
          >
            {t("Homepage.AwardedCoursesBtnText")}
          </Button>
        </div>

        {loading && (
          <Row>
            <CourseCardSkeleton
              count={8}
              colProps={{
                xs: 12,
                sm: 6,
                md: 3,
              }}
            />
          </Row>
        )}

        {!loading && isMobile && (
          <CoursesSlider courses={courses?.data || []} />
        )}
        {!loading && !isMobile && (
          <Row
            style={{
              rowGap: "20px",
            }}
          >
            {courses?.data.map((course) => (
              <Col md={6} lg={3} key={course.id}>
                <NewCourseCard
                  mobile={isMobile}
                  id={course.id}
                  image={
                    <Link to={`/courses/${course.id}`}>
                      {course.image_path ? (
                        <ResponsiveImage
                          path={course.image_path}
                          alt={course.title}
                          srcSizes={[300, 600, 900]}
                        />
                      ) : (
                        <CourseImgPlaceholder />
                      )}
                    </Link>
                  }
                  title={
                    <Link to={`/courses/${course.id}`}>
                      <Title level={3} as="h3" className="title">
                        {course.title}
                      </Title>
                    </Link>
                  }
                  categories={
                    <CategoriesBreadCrumbs
                      categories={course.categories}
                      onCategoryClick={(id) => {
                        history.push(`/courses/?categories[]=${id}`);
                      }}
                    />
                  }
                />
              </Col>
            ))}
          </Row>
        )}
        <Button
          className={styles.showMoreBtn}
          onClick={() => history.push(routeRoutes.courses)}
          block
          mode="outline"
        >
          {t("Homepage.AwardedCoursesBtnText")}
        </Button>
      </Container>
    </section>
  );
};

export default PromotedCoursesSection;
