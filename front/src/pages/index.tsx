import { useContext, useEffect } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import Layout from "@/components/_App/Layout";
import { Banner } from "@ulams/components/components/molecules/Banner/Banner";
import { ResponsiveImage } from "@ulams/components/components/organisms/ResponsiveImage/ResponsiveImage";
import styles from "./index.module.css";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";
import CategoriesSection from "@/components/Categories/CategoriesSection";
import { MarkdownRenderer } from "@ulams/components/components/molecules/MarkdownRenderer/MarkdownRenderer";
import { useHistory } from "react-router-dom";
import Container from "@/components/Common/Container";
import DisplayCourses from "@/components/Courses/DisplayCoursesSlider";
import routeRoutes from "@/components/Routes/routes";
import CoursesUserSlider from "@/components/Courses/CoursesUserSlider";

const Index = () => {
  const { categoryTree, settings, fetchCategories, user } =
    useContext(UlamsContext);

  const history = useHistory();
  const { t, i18n } = useTranslation();

  useEffect(() => {
    fetchCategories();
  }, [fetchCategories]);

  return (
    <Layout metaTitle={t("Home")}>
      <div className={styles.home}>
        <section className={styles.hero}>
          {settings.value?.homepage &&
            settings.value.homepage?.heroBannerText &&
            settings.value.homepage?.heroBannerImg &&
            settings.value.homepage?.heroBannerImg !== "" && (
              <Container>
                <Banner
                  mobile={isMobile}
                  title={
                    <MarkdownRenderer>
                      {`<h1>${
                        settings.value.homepage?.heroBannerText[
                          i18n.language
                        ] || ""
                      }</h1>`}
                    </MarkdownRenderer>
                  }
                  btnText={t("Homepage.HeroBtnText")}
                  asset={
                    <ResponsiveImage
                      path={settings?.value?.homepage?.heroBannerImg || ""}
                      srcSizes={[500, 750, 1000]}
                    />
                  }
                  handleBtn={() => history.push(routeRoutes.courses)}
                />
              </Container>
            )}
        </section>
        {user.value?.id && (
          <section className={styles.newestCourses}>
            <Container className={styles.wrapper}>
              <CoursesUserSlider titleText={t("Navbar.MyCourses")} />
            </Container>
          </section>
        )}

        <section className={styles.newestCourses}>
          <Container className={styles.wrapper}>
            <DisplayCourses
              titleText={t("Homepage.CoursesSlider2Title")}
              params={{
                per_page: 8,
                order_by: "created_at",
                order: "DESC",
              }}
            />
          </Container>
        </section>

        <section className="home-best-courses">
          <Container className={styles.wrapper}>
            <DisplayCourses
              titleText={t("Homepage.CoursesSlider1Title")}
              params={{
                per_page: 8,
              }}
            />
          </Container>
        </section>

        <div className="promoted-courses-wrapper">
          <Container className={styles.wrapper}>
            <DisplayCourses
              titleText={t("Homepage.AwardedCoursesTitle")}
              params={{
                per_page: 8,
              }}
              isSlider={false || isMobile ? true : false}
              ctaButton
            />
          </Container>
        </div>

        {categoryTree && (
          <div className="categories-section-wrapper">
            <CategoriesSection
              categories={
                categoryTree.list?.filter((category) => !!category.icon) || []
              }
              entity="courses"
            />
          </div>
        )}
      </div>
    </Layout>
  );
};

export default Index;
