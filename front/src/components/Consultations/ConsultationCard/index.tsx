import React, { useState } from "react";
import { NewCourseCard } from "@ulams/components";
import { API } from "@ulams/sdk";
import BookTermModal from "@/components/Book/BookTermModal";
import ConsultationCardButtons from "./Buttons";
import { Link, useHistory } from "react-router-dom";
import ConsultationCardContent from "./Content";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import ConsultationCardImage from "./Image";
import CategoriesBreadCrumbs from "@/components/Categories/CategoriesBreadCrumbs";
import { useTranslation } from "react-i18next";
import ProductPrices from "@/components/ProductPrices";
import styles from "./styles.module.css";

interface ConsultationCardProps {
  consultation: API.Consultation;
  showPrice?: boolean;
}

const ConsultationCard: React.FC<ConsultationCardProps> = (props) => {
  const { consultation, showPrice = false } = props;
  const [showBookTerm, setShowBookTerm] = useState(false);
  const history = useHistory();
  const { t } = useTranslation();

  return (
    <div className={styles.root}>
      <NewCourseCard
        id={consultation.id}
        categories={
          <CategoriesBreadCrumbs
            categories={consultation.categories ?? []}
            onCategoryClick={(id) => {
              history.push(`/consultations?categories[]=${id}`);
            }}
          />
        }
        image={<ConsultationCardImage consultation={consultation} />}
        title={
          <Link to={`/consultations/${consultation.id}`}>
            <Title level={3} as="h3" className="title">
              {consultation.name}
            </Title>
          </Link>
        }
        actions={
          <ConsultationCardButtons
            consultation={consultation}
            showBookTerm={(value) => setShowBookTerm(value)}
          />
        }
        footer={<ConsultationCardContent consultation={consultation} />}
        price={
          showPrice && (
            <>
              {/* @ts-ignore TODO: missed in sdk */}
              {consultation.public ? (
                <div className="course-price">{t("FREE")}</div>
              ) : (
                <ProductPrices
                  price={consultation.product?.price}
                  oldPrice={consultation.product?.price_old}
                  taxRate={consultation.product?.tax_rate}
                />
              )}
            </>
          )
        }
      />
      {/* Book term modal */}
      <BookTermModal
        visible={showBookTerm}
        onClose={() => setShowBookTerm(false)}
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        consultation={consultation as any}
      />
    </div>
  );
};

export default ConsultationCard;
