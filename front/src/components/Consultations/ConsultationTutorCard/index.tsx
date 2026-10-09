import React from "react";
import { CourseCard } from "@ulams/components/components/molecules/CourseCard/CourseCard";
import { API } from "@ulams/sdk";
import styles from "./styles.module.css";
import ConsultationTutorCardImage from "./Image";
import ConsultationTutorCardContent from "./Content";
import ConsultationTutorCardStatus from "./Status";

interface ConsultationTutorCardProps {
  consultation: API.AppointmentTerm;
}

const ConsultationTutorCard: React.FC<ConsultationTutorCardProps> = (props) => {
  const { consultation } = props;

  return (
    <div className={styles.root}>
      <CourseCard
        id={consultation?.consultation_term_id}
        image={<ConsultationTutorCardImage product={consultation} />}
        title={<ConsultationTutorCardStatus consultation={consultation} />}
        subtitle={<p>{consultation?.related_product?.name}</p>}
        footer={<ConsultationTutorCardContent consultation={consultation} />}
      />
    </div>
  );
};

export default ConsultationTutorCard;
