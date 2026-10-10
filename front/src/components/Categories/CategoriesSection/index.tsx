import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { IconText } from "@ulams/components/components/atoms/IconText/IconText";
import { CategoryCard } from "@ulams/components/components/molecules/CategoryCard/CategoryCard";
import { isMobile } from "react-device-detect";
import { useTranslation } from "react-i18next";
import styles from "./CategoriesSection.module.css";
import { IconSquares } from "../../../icons";
import { useHistory } from "react-router-dom";
import { API } from "@ulams/sdk";

import Container from "../../Common/Container";
import { Swiper, SwiperSlide } from "swiper/react";

type Props = {
  categories: API.Category[];
  entity: "courses" | "consultations" | "events" | "packages" | "webinars";
};

const CategoriesSection: React.FC<Props> = ({ categories, entity }) => {
  const { t } = useTranslation();
  const history = useHistory();

  const filteredCategories = categories.filter(
    (category) => category.count && category.count > 0
  );
  return (
    <section className={styles.section}>
      <Container>
        <Title level={1} as="h2">
          <strong>{t("Homepage.CategoriesTitle")}</strong>
        </Title>
        {isMobile ? (
          <div className={styles.slider}>
            <Swiper
              spaceBetween={18}
              slidesOffsetAfter={18}
              breakpoints={{
                0: {
                  slidesPerView: 1.3,
                },
                576: {
                  slidesPerView: 2,
                },
                768: {
                  slidesPerView: 3,
                },
                1201: {
                  slidesPerView: 4,
                },
              }}
            >
              {filteredCategories.slice(-5).map((item) => (
                <SwiperSlide key={item.id}>
                  <CategoryCard
                    icon={<img src={item.icon} alt={item.name} />}
                    title={item.name}
                    buttonText={t("Homepage.CategoryBtnText")}
                    subtitle={
                      <IconText
                        icon={<IconSquares />}
                        text={`${t("CoursesLength", {
                          count: item.count,
                        })}`}
                      />
                    }
                    onButtonClick={() =>
                      history.push(`/${entity}/?categories[]=${item.id}`)
                    }
                    variant="gradient"
                  />
                </SwiperSlide>
              ))}
            </Swiper>
          </div>
        ) : (
          <div className={styles.row}>
            {filteredCategories.slice(-5).map((item) => (
              <div className="category-item" key={item.id}>
                <CategoryCard
                  icon={<img src={item.icon} alt={item.name} />}
                  title={item.name}
                  buttonText={t("Homepage.CategoryBtnText")}
                  subtitle={
                    <IconText
                      icon={<IconSquares />}
                      text={`${t("CoursesLength", {
                        count: item.count,
                      })}`}
                    />
                  }
                  onButtonClick={() =>
                    history.push(`/${entity}/?categories[]=${item.id}`)
                  }
                  variant="gradient"
                />
              </div>
            ))}
          </div>
        )}
      </Container>
    </section>
  );
};

export default CategoriesSection;
