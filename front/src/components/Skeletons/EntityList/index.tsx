import React from "react";
import { Row } from "react-grid-system";

import { isMobile } from "react-device-detect";

import styles from "@/components/Skeletons/Skeletons.module.css";

import { CourseCardSkeleton } from "@/components/Skeletons/CourseCard";

const EntitySkeletonList = () => {
  return (
    <section className={`${styles.list}${isMobile ? ` ${styles.mobile}` : ""}`}>
      <Row
        style={{
          gap: "30px 0",
        }}
      >
        {Array.from({ length: 12 }).map((_, index) => (
          <CourseCardSkeleton
            key={`index-${index}-skeleton`}
            colProps={{
              xl: 3,
              lg: 4,
              md: 6,
            }}
          />
        ))}
      </Row>
    </section>
  );
};

export default EntitySkeletonList;
