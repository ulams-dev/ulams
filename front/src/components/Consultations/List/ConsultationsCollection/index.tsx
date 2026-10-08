import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { isMobile } from "react-device-detect";
import styles from "./styles.module.css";
import { ConsultationsContext } from "@/components/Consultations/List/ConsultationsContext";
import { useContext } from "react";
import ConsultationsSlider from "@/components/Consultations/ConsultationsSlider";
import { Tabs } from "@ulams/components/components/atoms/Tabs/Tabs";
import { API } from "@ulams/sdk";
import ConsultationCard from "@/components/Consultations/ConsultationCard";
import { Col, Row } from "react-grid-system";
import Preloader from "@/components/_App/Preloader";
import { useTranslation } from "react-i18next";

const ConsultationsCollection = () => {
  const { consultations, loading } = useContext(ConsultationsContext);
  const { t } = useTranslation();

  const consultationsCategories = consultations?.data?.map((item) =>
    item?.categories?.reduce(
      (acc: string[], cat: Ulams.Categories.Models.Category) =>
        cat.parent_id === null ? [...acc, cat.name] : acc,
      []
    )
  );

  //@ts-ignore
  const mergedCategories = [].concat.apply([], consultationsCategories);
  // @ts-ignore
  const categoriesWithoutDuplicates = [...new Set(mergedCategories)];

  const ConsultationsLayoutGrid = () => {
    return (
      <Row
        style={{
          marginTop: 50,
          gap: "30px 0",
        }}
      >
        {consultations?.data
          .sort((a: API.Consultation, b: API.Consultation) =>
            a.name.localeCompare(b.name)
          )
          .map((consultation: API.Consultation) => (
            <Col key={consultation.id} xs={12} sm={6} md={4} lg={3}>
              <ConsultationCard consultation={consultation} />
            </Col>
          ))}
      </Row>
    );
  };

  const ConsultationsLayoutSlider = () => {
    return (
      <>
        {categoriesWithoutDuplicates &&
          categoriesWithoutDuplicates.map((category) => (
            <ConsultationsSlider
              //@ts-ignore
              key={category.id}
              category={category}
              consultations={consultations?.data || []}
            />
          ))}
      </>
    );
  };

  const consultationsTabs = {
    tabs: [
      {
        label: t("ConsultationPage.ByFields"),
        key: 1,
        component: <ConsultationsLayoutSlider />,
      },
      {
        label: t("ConsultationPage.Alphabetically"),
        key: 2,
        component: <ConsultationsLayoutGrid />,
      },
    ],
    defaultActiveKey: 1,
  };

  if (loading) {
    return <Preloader />;
  }

  return (
    <>
      <div className={`${styles.header} ${isMobile ? styles.mobile : ""}`}>
        <Title level={1}>Ucz się od najlepszych</Title>
      </div>
      <div className={`${styles.tabs} ${isMobile ? styles.mobile : ""}`}>
        <Tabs
          tabs={consultationsTabs.tabs}
          defaultActiveKey={consultationsTabs.defaultActiveKey}
        />
      </div>
    </>
  );
};

export default ConsultationsCollection;
